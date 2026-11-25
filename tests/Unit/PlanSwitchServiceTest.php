<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\Subscriptions\PlanSwitchService;
use Cable8mm\LaravelPortone\PaymentOperations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PlanSwitchServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 00:00:00'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(string $id, int $amount, array $seatPricing = ['enabled' => false]): array
    {
        return [
            'id' => $id,
            'name' => $id,
            'description' => $id,
            'prices' => ['monthly' => $amount],
            'trial_days' => null,
            'features' => ['included' => [], 'unavailable' => []],
            'options' => [],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => $seatPricing,
        ];
    }

    private function subscription(User $user, int $amount, int $seats = 1): SparkoSubscription
    {
        return SparkoSubscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'plan_id' => 'basic',
            'billing_interval' => 'monthly',
            'renewal_amount' => $amount,
            'seat_quantity' => $seats,
            'credit_balance' => 0,
            'billing_key_issue_id' => 'bk-test',
            'next_charge_at' => CarbonImmutable::now()->addDays(30),
            'status' => 'ACTIVE',
        ]);
    }

    public function test_upgrade_charges_the_prorated_difference_and_keeps_the_billing_anchor(): void
    {
        config()->set('plans.plans', [$this->plan('basic', 10000), $this->plan('pro', 20000)]);
        $user = User::factory()->create();
        $subscription = $this->subscription($user, 10000);
        $anchor = $subscription->getRawOriginal('next_charge_at');

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldReceive('chargeBillingKey')->once()->with(Mockery::any(), 'bk-test', 10000, 'Plan adjustment');
        $this->app->instance(PaymentOperations::class, $payments);

        $updated = app(PlanSwitchService::class)->switch($user, 'pro', null);

        $this->assertSame('pro', $updated->plan_id);
        $this->assertSame(20000, $updated->renewal_amount);
        $this->assertSame($anchor, $updated->getRawOriginal('next_charge_at'));
    }

    public function test_downgrade_carries_the_credit_without_a_charge(): void
    {
        config()->set('plans.plans', [$this->plan('basic', 10000), $this->plan('pro', 20000)]);
        $user = User::factory()->create();
        $this->subscription($user, 20000);

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldNotReceive('chargeBillingKey');
        $this->app->instance(PaymentOperations::class, $payments);

        $updated = app(PlanSwitchService::class)->switch($user, 'basic', null);

        $this->assertSame(10000, $updated->credit_balance);
        $this->assertSame('basic', $updated->plan_id);
    }

    public function test_seat_change_uses_unit_pricing_and_enforces_a_minimum_of_one(): void
    {
        config()->set('plans.plans', [$this->plan('basic', 10000, ['enabled' => true, 'unit_price' => 5000])]);
        $user = User::factory()->create();
        $this->subscription($user, 10000, 2);

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldReceive('chargeBillingKey')->once()->with(Mockery::any(), 'bk-test', 5000, 'Plan adjustment');
        $this->app->instance(PaymentOperations::class, $payments);

        $updated = app(PlanSwitchService::class)->switch($user, 'basic', 3);

        $this->assertSame(3, $updated->seat_quantity);
        $this->assertSame(15000, $updated->renewal_amount);

        $payments2 = Mockery::mock(PaymentOperations::class);
        $payments2->shouldNotReceive('chargeBillingKey');
        $this->app->instance(PaymentOperations::class, $payments2);

        $minimum = app(PlanSwitchService::class)->switch($user, 'basic', 0);
        $this->assertSame(1, $minimum->seat_quantity);
    }

    public function test_a_failed_adjustment_charge_leaves_the_plan_unchanged(): void
    {
        config()->set('plans.plans', [$this->plan('basic', 10000), $this->plan('pro', 20000)]);
        $user = User::factory()->create();
        $subscription = $this->subscription($user, 10000);

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldReceive('chargeBillingKey')->once()->andThrow(new \RuntimeException('declined'));
        $this->app->instance(PaymentOperations::class, $payments);

        try {
            app(PlanSwitchService::class)->switch($user, 'pro', null);
            $this->fail('The charge failure should propagate.');
        } catch (\RuntimeException) {
            $this->assertSame('basic', $subscription->fresh()->plan_id);
        }
    }
}
