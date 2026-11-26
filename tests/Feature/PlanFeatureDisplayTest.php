<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureDisplayTest extends TestCase
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
            'features' => [
                'included' => ['Unlimited reports'],
                'unavailable' => ['Team seats'],
            ],
            'options' => [],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
        ]]);
    }

    public function test_included_and_unavailable_features_are_shown_distinctly(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('sparko.portal', $user));

        $response->assertOk()
            ->assertSee('data-feature-included>Unlimited reports<', false)
            ->assertSee('data-feature-unavailable>Team seats<', false)
            ->assertDontSee('data-feature-included>Team seats<', false)
            ->assertDontSee('data-feature-unavailable>Unlimited reports<', false);
    }
}
