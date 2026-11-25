<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeBillingKeyGateway;
use Tests\TestCase;

class BillingKeyEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->app->bind(BillingKeyGateway::class, FakeBillingKeyGateway::class);
        config()->set('plans.plans', [[
            'id' => 'pro',
            'name' => 'Pro',
            'description' => 'Pro plan',
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
    }

    public function test_enrollment_stores_the_key_only_after_provider_confirmation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('sparko.payment-method.store', $user), [
            'billing_key' => 'billing-key-plaintext-must-not-be-stored',
            'customer_id' => 'customer-1',
            'issue_name' => 'card',
        ]);

        $response->assertCreated()->assertJsonPath('status', 'ISSUED')->assertJsonMissing(['billing-key-plaintext-must-not-be-stored']);
        $this->assertSame('ISSUED', BillingKey::query()->sole()->status);
    }

    public function test_subscription_starts_after_enrollment_and_trial_is_recorded(): void
    {
        $user = User::factory()->create();

        $enrollment = $this->actingAs($user)->postJson(route('sparko.payment-method.store', $user), [
            'billing_key' => 'billing-key-plaintext-must-not-be-stored',
            'customer_id' => 'customer-1',
            'issue_name' => 'card',
        ]);

        $response = $this->actingAs($user)->postJson(route('sparko.subscription.store', $user), [
            'plan_id' => 'pro',
            'billing_key_issue_id' => $enrollment->json('issue_id'),
        ]);

        $response->assertCreated()->assertJsonPath('status', 'TRIAL')->assertJsonPath('plan_id', 'pro');
        $this->assertNotNull($response->json('trial_ends_at'));
    }
}
