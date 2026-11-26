<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Cable8mm\LaravelPortone\Models\Payment;
use Cable8mm\PortonePhp\Webhook\WebhookSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class LiveDocumentsE2ETest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Authorization' => 'PortOne '.(string) config('portone.api_secret'), 'Accept' => 'application/json'];
    }

    private function issue(string $customerId): string
    {
        $response = Http::withHeaders($this->headers())->post('https://api.portone.io/billing-keys', [
            'channelKey' => config('sparko.e2e_channel_key'),
            'customer' => [
                'id' => $customerId,
                'name' => ['full' => '포트원'],
                'phoneNumber' => '010-0000-1234',
                'email' => 'test@portone.io',
            ],
            'method' => ['card' => ['credential' => [
                'number' => '4673090000000032',
                'expiryYear' => '30',
                'expiryMonth' => '12',
                'birthOrBusinessRegistrationNumber' => '900101',
                'passwordTwoDigits' => '00',
            ]]],
        ])->throw();

        return (string) $response->json('billingKeyInfo.billingKey');
    }

    #[Group('live')]
    public function test_a_live_charge_appears_in_history_with_receipt_and_invoice(): void
    {
        if (config('sparko.live_billing_key_e2e') !== '1' || blank(config('portone.api_secret')) || blank(config('sparko.e2e_channel_key'))) {
            $this->markTestSkipped('Set SPARKO_LIVE_BILLING_KEY_E2E=1, PORTONE_API_SECRET and PORTONE_E2E_CHANNEL_KEY to run the live documents test.');
        }

        $this->withoutVite();

        $customerId = 'sparko-docs-'.bin2hex(random_bytes(6));
        $key = $this->issue($customerId);
        $paymentId = 'sparko-docs-'.bin2hex(random_bytes(16));

        try {
            Http::withHeaders($this->headers())->post('https://api.portone.io/payments/'.$paymentId.'/billing-key', [
                'billingKey' => $key,
                'orderName' => 'Document E2E',
                'amount' => ['total' => 1000],
                'currency' => 'KRW',
            ])->throw();

            $lookup = Http::withHeaders($this->headers())->get('https://api.portone.io/payments/'.$paymentId)->throw();

            $user = User::factory()->create();
            $enrollment = $this->actingAs($user)->postJson(route('sparko.payment-method.store', $user), [
                'billing_key' => $key,
                'customer_id' => $customerId,
                'issue_name' => 'card',
            ]);
            $enrollment->assertCreated();

            $payment = Payment::query()->create([
                'payment_id' => $paymentId,
                'billable_type' => $user->getMorphClass(),
                'billable_id' => (string) $user->getKey(),
                'customer_id' => $customerId,
                'order_name' => 'Document E2E',
                'amount' => 1000,
                'status' => 'PENDING',
                'cancelled_amount' => 0,
                'billing_key_id' => BillingKey::query()->where('issue_id', $enrollment->json('issue_id'))->value('id'),
            ]);

            $body = json_encode([
                'type' => 'Transaction.Paid',
                'timestamp' => now()->toIso8601String(),
                'data' => [
                    'paymentId' => $paymentId,
                    'storeId' => (string) $lookup->json('storeId'),
                    'transactionId' => (string) $lookup->json('transactionId'),
                ],
            ]);
            $headers = (new WebhookSigner((string) config('portone.webhook_secret')))
                ->headers($body, 'msg-'.bin2hex(random_bytes(8)));

            $this->call('POST', route('portone.webhook'), [], [], [], [
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'CONTENT_TYPE' => 'application/json',
            ], $body)->assertOk();

            $payment->refresh();
            $this->assertSame('PAID', $payment->status);
            $this->assertNotNull($payment->receipt_url);

            $this->actingAs($user)->get(route('sparko.portal', $user))
                ->assertOk()
                ->assertSee('₩1,000')
                ->assertSee('data-receipt', false)
                ->assertSee(route('sparko.invoice.download', [$user, $payment]));

            $invoice = $this->actingAs($user)->get(route('sparko.invoice.download', [$user, $payment]));
            $invoice->assertOk();
            $this->assertStringStartsWith('%PDF', $invoice->getContent());

            $this->actingAs(User::factory()->create())
                ->get(route('sparko.invoice.download', [$user, $payment]))
                ->assertForbidden();

            fwrite(STDERR, sprintf("\n[live documents] %s status=%s receipt=%s pdf=%d bytes\n", now()->toIso8601String(), $payment->status, filled($payment->receipt_url) ? 'present' : 'missing', strlen((string) $invoice->getContent())));
        } finally {
            Http::withHeaders($this->headers())->delete('https://api.portone.io/billing-keys/'.rawurlencode($key));
        }
    }
}
