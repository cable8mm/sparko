<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SparkoSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('plans.plans', [[
            'id' => 'pro',
            'name' => 'Pro',
            'description' => 'Pro plan',
            'prices' => ['monthly' => 10000],
            'trial_days' => null,
            'features' => ['included' => [], 'unavailable' => []],
            'options' => ['reports' => true],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
        ]]);
    }

    private function subscribe(User $user): void
    {
        SparkoSubscription::query()->create([
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

    public function test_an_active_subscriber_passes_the_gate(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);

        $this->actingAs($user)->get(route('member'))->assertOk();
    }

    public function test_a_non_subscriber_is_redirected_to_the_portal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('member'))
            ->assertRedirect(route('sparko.portal', $user));
    }

    public function test_an_async_request_without_a_subscription_returns_402(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('member'))->assertStatus(402);
    }

    public function test_entitlements_are_readable_by_the_owner_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->subscribe($user);

        $this->actingAs($user)->get(route('sparko.entitlements', $user))
            ->assertOk()
            ->assertJsonPath('options.reports', true);

        $this->actingAs($user)->get(route('sparko.entitlements', $other))->assertForbidden();
    }
}
