<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SparkoSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('discounts.enabled', true);
        config()->set('discounts.codes', [[
            'code' => 'SAVE',
            'type' => 'fixed',
            'value' => 3000,
            'duration' => 'once',
            'charges' => null,
            'eligibility' => [],
            'active_from' => null,
            'active_until' => null,
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

    public function test_a_valid_code_is_attached_and_an_invalid_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);

        $this->actingAs($user)->postJson(route('sparko.discount.store', $user), ['code' => 'SAVE'])
            ->assertOk()
            ->assertJsonPath('code', 'SAVE')
            ->assertJsonPath('value', 3000);

        $this->actingAs($user)->postJson(route('sparko.discount.store', $user), ['code' => 'MISSING'])
            ->assertStatus(422);

        $this->assertSame('SAVE', SparkoSubscription::query()->sole()->discount_code);
    }
}
