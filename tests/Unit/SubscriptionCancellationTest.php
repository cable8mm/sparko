<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\Subscriptions\RenewalService;
use App\Sparko\Subscriptions\SubscriptionCancellation;
use Cable8mm\LaravelPortone\PaymentOperations;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class SubscriptionCancellationTest extends TestCase
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

    private function subscription(User $user): SparkoSubscription
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
            'next_charge_at' => CarbonImmutable::now()->addDays(10),
            'trial_ends_at' => null,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_cancellation_schedules_the_end_without_a_refund(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscription($user);

        $updated = app(SubscriptionCancellation::class)->cancel($user);

        $this->assertSame('CANCELLED', $updated->status);
        $this->assertSame($subscription->getRawOriginal('next_charge_at'), $updated->getRawOriginal('next_charge_at'));
        $this->assertSame(0, $updated->credit_balance);
    }

    public function test_resume_restores_an_active_subscription_before_period_end(): void
    {
        $user = User::factory()->create();
        $this->subscription($user);
        $cancellation = app(SubscriptionCancellation::class);
        $cancellation->cancel($user);

        $this->assertSame('ACTIVE', $cancellation->resume($user)->status);
    }

    public function test_resume_is_rejected_after_the_period_end(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscription($user);
        $cancellation = app(SubscriptionCancellation::class);
        $cancellation->cancel($user);
        $subscription->update(['next_charge_at' => CarbonImmutable::now()->subMinute()]);

        $this->expectException(DomainException::class);

        $cancellation->resume($user);
    }

    public function test_a_cancelled_period_ends_without_a_charge(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscription($user);
        $subscription->update([
            'status' => 'CANCELLED',
            'next_charge_at' => CarbonImmutable::now()->subMinute(),
        ]);

        $payments = Mockery::mock(PaymentOperations::class);
        $payments->shouldNotReceive('chargeBillingKey');
        $this->app->instance(PaymentOperations::class, $payments);

        app(RenewalService::class)->chargeDue(CarbonImmutable::now());

        $subscription->refresh();
        $this->assertSame('ENDED', $subscription->status);
        $this->assertNull($subscription->next_charge_at);
    }
}
