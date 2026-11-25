<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\Subscriptions\RenewalService;
use Cable8mm\LaravelPortone\PaymentOperations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class RenewalServiceTest extends TestCase
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

    private function subscription(User $user, int $amount, int $credit = 0): SparkoSubscription
    {
        return SparkoSubscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'plan_id' => 'pro',
            'billing_interval' => 'monthly',
            'renewal_amount' => $amount,
            'seat_quantity' => 1,
            'credit_balance' => $credit,
            'billing_key_issue_id' => 'bk-renew',
            'next_charge_at' => CarbonImmutable::now()->subMinute(),
            'status' => 'ACTIVE',
        ]);
    }

    public function test_a_due_period_is_charged_once_and_the_period_advances(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscription($user, 10000);

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldReceive('chargeBillingKey')->once()->with(Mockery::any(), 'bk-renew', 10000, 'Subscription renewal');
        $this->app->instance(PaymentOperations::class, $payments);

        $service = app(RenewalService::class);

        $this->assertSame(1, $service->chargeDue(CarbonImmutable::now()));
        $this->assertSame(0, $service->chargeDue(CarbonImmutable::now()));

        $subscription->refresh();
        $this->assertNotNull($subscription->last_charged_period);
        $this->assertTrue(CarbonImmutable::parse((string) $subscription->getRawOriginal('next_charge_at'))->isAfter(CarbonImmutable::now()));
    }

    public function test_an_outage_leaves_the_period_unpaid(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscription($user, 10000);

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldReceive('chargeBillingKey')->once()->andThrow(new RuntimeException('provider unavailable'));
        $this->app->instance(PaymentOperations::class, $payments);

        try {
            app(RenewalService::class)->chargeDue(CarbonImmutable::now());
            $this->fail('The provider failure should propagate.');
        } catch (RuntimeException) {
            $this->assertNull($subscription->fresh()->last_charged_period);
        }
    }

    public function test_a_credit_reduces_the_renewal_charge(): void
    {
        $user = User::factory()->create();
        $this->subscription($user, 10000, 3000);

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldReceive('chargeBillingKey')->once()->with(Mockery::any(), 'bk-renew', 7000, 'Subscription renewal');
        $this->app->instance(PaymentOperations::class, $payments);

        app(RenewalService::class)->chargeDue(CarbonImmutable::now());

        $this->assertSame(0, SparkoSubscription::query()->sole()->credit_balance);
    }
}
