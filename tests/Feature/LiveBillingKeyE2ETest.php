<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class LiveBillingKeyE2ETest extends TestCase
{
    use RefreshDatabase;

    #[Group('live')]
    public function test_a_live_billing_key_is_confirmed_before_a_trial_subscription_starts(): void
    {
        if (config('sparko.live_billing_key_e2e') !== '1' || blank(config('portone.api_secret')) || blank(config('sparko.e2e_channel_key'))) {
            $this->markTestSkipped('Set SPARKO_LIVE_BILLING_KEY_E2E=1, PORTONE_API_SECRET and PORTONE_E2E_CHANNEL_KEY to run the live billing-key test.');
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

        $secret = (string) config('portone.api_secret');
        $headers = ['Authorization' => 'PortOne '.$secret, 'Accept' => 'application/json'];
        $customerId = 'sparko-live-'.bin2hex(random_bytes(6));

        try {
            $issued = Http::withHeaders($headers)->post('https://api.portone.io/billing-keys', [
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

            $billingKey = (string) $issued->json('billingKeyInfo.billingKey');
            $this->assertNotSame('', $billingKey);

            $user = User::factory()->create();
            $enrollment = $this->actingAs($user)->postJson(route('sparko.payment-method.store', $user), [
                'billing_key' => $billingKey,
                'customer_id' => $customerId,
                'issue_name' => 'live-card',
            ]);
            $enrollment->assertCreated()->assertJsonPath('status', 'ISSUED');

            $subscription = $this->actingAs($user)->postJson(route('sparko.subscription.store', $user), [
                'plan_id' => 'live',
                'billing_key_issue_id' => $enrollment->json('issue_id'),
            ]);
            $subscription->assertCreated()->assertJsonPath('status', 'TRIAL');

            fwrite(STDERR, sprintf(
                "\n[live billing key] %s enrollment=%s subscription=%s status=%s\n",
                now()->toIso8601String(),
                $enrollment->status(),
                $subscription->status(),
                $subscription->json('status'),
            ));
        } finally {
            if (isset($billingKey)) {
                Http::withHeaders($headers)->delete('https://api.portone.io/billing-keys/'.rawurlencode($billingKey));
            }
        }
    }
}
