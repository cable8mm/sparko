<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\Discounts\DiscountService;
use App\Sparko\Discounts\InvalidDiscount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-26 00:00:00'));
        config()->set('discounts.enabled', true);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function codes(array $overrides = []): void
    {
        config()->set('discounts.codes', [[
            'code' => 'SAVE',
            'type' => 'fixed',
            'value' => 3000,
            'duration' => 'once',
            'charges' => 2,
            'eligibility' => [],
            'active_from' => null,
            'active_until' => null,
            ...$overrides,
        ]]);
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
            'status' => 'ACTIVE',
        ]);
    }

    public function test_a_fixed_discount_reduces_the_charge_and_is_consumed_once(): void
    {
        $this->codes();
        $user = User::factory()->create();
        $this->subscription($user);
        $service = app(DiscountService::class);

        $subscription = $service->apply($user, 'SAVE');

        $this->assertSame(7000, $service->resolve(10000, $subscription));
        $service->consume($subscription);
        $this->assertSame(10000, $service->resolve(10000, $subscription->fresh()));
    }

    public function test_a_percentage_discount_with_finite_charges_decrements(): void
    {
        $this->codes(['type' => 'percentage', 'value' => 25, 'duration' => 'charges', 'charges' => 2]);
        $user = User::factory()->create();
        $this->subscription($user);
        $service = app(DiscountService::class);

        $subscription = $service->apply($user, 'SAVE');
        $this->assertSame(7500, $service->resolve(10000, $subscription));

        $service->consume($subscription);
        $subscription->refresh();
        $this->assertSame(1, $subscription->discount_remaining_charges);
        $this->assertSame(7500, $service->resolve(10000, $subscription));

        $service->consume($subscription);
        $this->assertSame(10000, $service->resolve(10000, $subscription->fresh()));
    }

    public function test_a_lifetime_discount_is_not_consumed(): void
    {
        $this->codes(['duration' => 'lifetime']);
        $user = User::factory()->create();
        $this->subscription($user);
        $service = app(DiscountService::class);

        $subscription = $service->apply($user, 'SAVE');
        $service->consume($subscription);

        $this->assertSame(7000, $service->resolve(10000, $subscription->fresh()));
    }

    public function test_a_discount_never_reduces_a_charge_below_zero(): void
    {
        $this->codes(['value' => 20000]);
        $user = User::factory()->create();
        $this->subscription($user);
        $service = app(DiscountService::class);

        $this->assertSame(0, $service->resolve(10000, $service->apply($user, 'SAVE')));
    }

    public function test_expired_and_ineligible_codes_are_rejected(): void
    {
        $this->codes(['active_until' => '2026-11-25T00:00:00+00:00']);
        $user = User::factory()->create();
        $this->subscription($user);

        try {
            app(DiscountService::class)->apply($user, 'SAVE');
            $this->fail('An expired code should be rejected.');
        } catch (InvalidDiscount) {
            $this->assertNull(SparkoSubscription::query()->sole()->discount_code);
        }

        $this->codes(['eligibility' => ['plan_ids' => ['other']]]);

        $this->expectException(InvalidDiscount::class);
        app(DiscountService::class)->apply($user, 'SAVE');
    }

    public function test_only_one_discount_is_active(): void
    {
        config()->set('discounts.codes', [
            ['code' => 'A', 'type' => 'fixed', 'value' => 1000, 'duration' => 'lifetime', 'eligibility' => [], 'active_from' => null, 'active_until' => null],
            ['code' => 'B', 'type' => 'fixed', 'value' => 2000, 'duration' => 'lifetime', 'eligibility' => [], 'active_from' => null, 'active_until' => null],
        ]);
        $user = User::factory()->create();
        $this->subscription($user);
        $service = app(DiscountService::class);

        $service->apply($user, 'A');
        $subscription = $service->apply($user, 'B');

        $this->assertSame('B', $subscription->discount_code);
        $this->assertSame(8000, $service->resolve(10000, $subscription));
    }
}
