<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\Subscriptions\RenewalService;
use Cable8mm\LaravelPortone\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class LiveRenewalChargeE2ETest extends TestCase
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
    public function test_a_live_due_period_is_charged_once(): void
    {
        if (config('sparko.live_billing_key_e2e') !== '1' || blank(config('portone.api_secret')) || blank(config('sparko.e2e_channel_key'))) {
            $this->markTestSkipped('Set SPARKO_LIVE_BILLING_KEY_E2E=1, PORTONE_API_SECRET and PORTONE_E2E_CHANNEL_KEY to run the live renewal test.');
        }

        $this->withoutVite();
        config()->set('plans.plans', [[
            'id' => 'pro', 'name' => 'Pro', 'description' => 'Pro plan',
            'prices' => ['monthly' => 10000], 'trial_days' => 5,
            'features' => ['included' => [], 'unavailable' => []],
            'options' => [], 'eligibility' => [], 'incentive' => null,
            'archived' => false, 'seat_pricing' => ['enabled' => false],
        ]]);

        $customerId = 'sparko-renew-'.bin2hex(random_bytes(6));
        $key = $this->issue($customerId);

        try {
            $user = User::factory()->create();
            $enrollment = $this->actingAs($user)->postJson(route('sparko.payment-method.store', $user), [
                'billing_key' => $key,
                'customer_id' => $customerId,
                'issue_name' => 'card',
            ]);
            $enrollment->assertCreated();

            $this->actingAs($user)->postJson(route('sparko.subscription.store', $user), [
                'plan_id' => 'pro',
                'billing_key_issue_id' => $enrollment->json('issue_id'),
            ])->assertCreated();

            SparkoSubscription::query()->update(['next_charge_at' => CarbonImmutable::now()->subMinute()]);

            $service = app(RenewalService::class);
            $now = CarbonImmutable::now();

            $this->assertSame(1, $service->chargeDue($now));
            $this->assertSame(0, $service->chargeDue($now));

            $this->assertSame('PAID', Payment::query()->sole()->status);
            $this->assertSame(10000, Payment::query()->sole()->amount);

            fwrite(STDERR, sprintf("\n[live renewal] %s status=%s amount=%d\n", now()->toIso8601String(), Payment::query()->sole()->status, Payment::query()->sole()->amount));
        } finally {
            Http::withHeaders($this->headers())->delete('https://api.portone.io/billing-keys/'.rawurlencode($key));
        }
    }
}
