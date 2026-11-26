<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\Plans\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-26 00:00:00'));
        config()->set('plans.plans', [[
            'id' => 'pro',
            'name' => 'Pro',
            'description' => 'Pro plan',
            'prices' => ['monthly' => 10000],
            'trial_days' => null,
            'features' => ['included' => [], 'unavailable' => []],
            'options' => ['reports' => true, 'seats' => 5],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
        ]]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function subscription(User $user, string $status, ?CarbonImmutable $endsAt = null): SparkoSubscription
    {
        return SparkoSubscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'plan_id' => 'pro',
            'billing_interval' => 'monthly',
            'renewal_amount' => 10000,
            'seat_quantity' => 1,
            'credit_balance' => 0,
            'billing_key_issue_id' => 'bk-test',
            'next_charge_at' => $endsAt ?? CarbonImmutable::now()->addDays(10),
            'status' => $status,
        ]);
    }

    public function test_active_plan_options_are_exposed(): void
    {
        $user = User::factory()->create();
        $this->subscription($user, 'ACTIVE');

        $this->assertSame(['reports' => true, 'seats' => 5], app(EntitlementService::class)->optionsFor($user));
    }

    public function test_no_subscription_has_no_options(): void
    {
        $this->assertSame([], app(EntitlementService::class)->optionsFor(User::factory()->create()));
    }

    public function test_active_and_trial_subscriptions_pass_the_gate(): void
    {
        $service = app(EntitlementService::class);
        $active = User::factory()->create();
        $this->subscription($active, 'ACTIVE');
        $trial = User::factory()->create();
        $this->subscription($trial, 'TRIAL');

        $this->assertTrue($service->isActive($active));
        $this->assertTrue($service->isActive($trial));
    }

    public function test_cancellation_remains_active_until_the_period_ends(): void
    {
        $service = app(EntitlementService::class);
        $pending = User::factory()->create();
        $this->subscription($pending, 'CANCELLED', CarbonImmutable::now()->addDay());
        $ended = User::factory()->create();
        $this->subscription($ended, 'CANCELLED', CarbonImmutable::now()->subMinute());

        $this->assertTrue($service->isActive($pending));
        $this->assertFalse($service->isActive($ended));
    }

    public function test_another_entity_has_no_entitlements(): void
    {
        $owner = User::factory()->create();
        $this->subscription($owner, 'ACTIVE');

        $this->assertSame([], app(EntitlementService::class)->optionsFor(User::factory()->create()));
    }
}
