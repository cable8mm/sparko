<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SparkoSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_and_resume_endpoints_change_the_subscription_state(): void
    {
        $user = User::factory()->create();
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

        $this->actingAs($user)->postJson(route('sparko.subscription.cancel', $user))
            ->assertOk()
            ->assertJsonPath('status', 'CANCELLED')
            ->assertJsonPath('refund', 0);

        $this->actingAs($user)->postJson(route('sparko.subscription.resume', $user))
            ->assertOk()
            ->assertJsonPath('status', 'ACTIVE');
    }
}
