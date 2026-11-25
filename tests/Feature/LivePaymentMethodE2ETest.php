<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SparkoSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class LivePaymentMethodE2ETest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'PortOne '.(string) config('portone.api_secret'),
            'Accept' => 'application/json',
        ];
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

    private function statusOf(string $billingKey): ?string
    {
        return Http::withHeaders($this->headers())
            ->get('https://api.portone.io/billing-keys/'.rawurlencode($billingKey))
            ->json('status');
    }

    #[Group('live')]
    public function test_a_live_replacement_deletes_the_previous_key_and_updates_the_subscription(): void
    {
        if (config('sparko.live_billing_key_e2e') !== '1' || blank(config('portone.api_secret')) || blank(config('sparko.e2e_channel_key'))) {
            $this->markTestSkipped('Set SPARKO_LIVE_BILLING_KEY_E2E=1, PORTONE_API_SECRET and PORTONE_E2E_CHANNEL_KEY to run the live replacement test.');
        }

        $this->withoutVite();
        config()->set('plans.plans', [[
            'id' => 'live',
            'name' => 'Live',
            'description' => 'Live plan',
            'prices' => ['monthly' => 10000],
            'trial_days' => 5,
            'trial_requires_payment_method' => true,
            'features' => ['included' => [], 'unavailable' => []],
            'options' => [],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
        ]]);

        $customerId = 'sparko-replace-'.bin2hex(random_bytes(6));
        $oldKey = $this->issue($customerId.'-old');
        $newKey = $this->issue($customerId.'-new');

        try {
            $user = User::factory()->create();

            $enrollment = $this->actingAs($user)->postJson(route('sparko.payment-method.store', $user), [
                'billing_key' => $oldKey,
                'customer_id' => $customerId.'-old',
                'issue_name' => 'old-card',
            ]);
            $enrollment->assertCreated();

            $this->actingAs($user)->postJson(route('sparko.subscription.store', $user), [
                'plan_id' => 'live',
                'billing_key_issue_id' => $enrollment->json('issue_id'),
            ])->assertCreated();

            $replacement = $this->actingAs($user)->postJson(route('sparko.payment-method.replace', $user), [
                'billing_key' => $newKey,
                'customer_id' => $customerId.'-new',
                'issue_name' => 'new-card',
            ]);
            $replacement->assertOk()->assertJsonPath('status', 'ISSUED');

            $this->assertSame('DELETED', $this->statusOf($oldKey));
            $this->assertSame('ISSUED', $this->statusOf($newKey));
            $this->assertSame($replacement->json('issue_id'), SparkoSubscription::query()->sole()->billing_key_issue_id);

            fwrite(STDERR, sprintf("\n[live replacement] %s old=%s new=%s\n", now()->toIso8601String(), $this->statusOf($oldKey), $this->statusOf($newKey)));
        } finally {
            Http::withHeaders($this->headers())->delete('https://api.portone.io/billing-keys/'.rawurlencode($newKey));
        }
    }
}
