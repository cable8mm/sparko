<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_portal_displays_plan_pricing_trial_features_and_incentive(): void
    {
        config()->set('plans.plans', [[
            'id' => 'pro',
            'name' => 'Pro',
            'description' => 'Pro plan',
            'prices' => ['monthly' => 10000, 'yearly' => 100000],
            'trial_days' => 5,
            'features' => ['included' => ['reports'], 'unavailable' => ['seats']],
            'options' => ['reports' => true],
            'eligibility' => [],
            'incentive' => 'Launch offer',
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
        ]]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sparko.portal', $user))
            ->assertOk()
            ->assertSee('Pro plan')
            ->assertSee('₩10,000')
            ->assertSee('₩100,000')
            ->assertSee('data-trial-days="5"', false)
            ->assertSee('reports')
            ->assertSee('seats')
            ->assertSee('Launch offer');
    }

    public function test_archived_plan_is_visible_but_marked_not_selectable(): void
    {
        config()->set('plans.plans', [[
            'id' => 'legacy',
            'name' => 'Legacy',
            'description' => 'Legacy plan',
            'prices' => ['monthly' => 5000],
            'trial_days' => null,
            'features' => ['included' => [], 'unavailable' => []],
            'options' => [],
            'eligibility' => [],
            'incentive' => null,
            'archived' => true,
            'seat_pricing' => ['enabled' => false],
        ]]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sparko.portal', $user))
            ->assertOk()
            ->assertSee('Legacy plan')
            ->assertSee('data-archived', false)
            ->assertSee('data-not-selectable', false);
    }
}
