<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Sparko\Plans\IneligiblePlan;
use App\Sparko\Plans\InvalidPlanConfiguration;
use App\Sparko\Plans\PlanCatalog;
use Tests\TestCase;

class PlanCatalogTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function plan(array $overrides = []): array
    {
        return [
            'id' => 'pro',
            'name' => 'Pro',
            'description' => 'Pro plan',
            'prices' => ['monthly' => 10000, 'yearly' => 100000],
            'trial_days' => 5,
            'features' => ['included' => ['reports'], 'unavailable' => ['seats']],
            'options' => ['reports' => true],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
            ...$overrides,
        ];
    }

    public function test_accepts_a_valid_plan(): void
    {
        config()->set('plans.plans', [$this->plan()]);

        $this->assertArrayHasKey('pro', app(PlanCatalog::class)->all());
    }

    public function test_rejects_a_plan_without_a_positive_price(): void
    {
        config()->set('plans.plans', [$this->plan(['prices' => ['monthly' => 0]])]);

        $this->expectException(InvalidPlanConfiguration::class);

        app(PlanCatalog::class)->all();
    }

    public function test_rejects_a_negative_trial(): void
    {
        config()->set('plans.plans', [$this->plan(['trial_days' => -1])]);

        $this->expectException(InvalidPlanConfiguration::class);

        app(PlanCatalog::class)->all();
    }

    public function test_rejects_seat_pricing_without_a_unit_price(): void
    {
        config()->set('plans.plans', [$this->plan(['seat_pricing' => ['enabled' => true]])]);

        $this->expectException(InvalidPlanConfiguration::class);

        app(PlanCatalog::class)->all();
    }

    public function test_archived_plan_is_visible_but_not_selectable(): void
    {
        config()->set('plans.plans', [$this->plan(['archived' => true])]);
        $user = User::factory()->make();

        $entries = app(PlanCatalog::class)->forPortal($user, 'pro');

        $this->assertCount(1, $entries);
        $this->assertTrue($entries[0]['is_current']);
        $this->assertFalse($entries[0]['selectable']);
    }

    public function test_ineligible_plan_is_not_selectable(): void
    {
        config()->set('plans.plans', [$this->plan(['eligibility' => ['billable_types' => ['App\\Models\\Team']]])]);
        $user = User::factory()->make();

        $entries = app(PlanCatalog::class)->forPortal($user, null);

        $this->assertFalse($entries[0]['selectable']);
    }

    public function test_assert_selectable_rejects_an_ineligible_plan(): void
    {
        config()->set('plans.plans', [$this->plan(['eligibility' => ['billable_types' => ['App\\Models\\Team']]])]);

        $this->expectException(IneligiblePlan::class);

        app(PlanCatalog::class)->assertSelectable('pro', User::factory()->make());
    }

    public function test_assert_selectable_returns_the_plan_when_eligible(): void
    {
        config()->set('plans.plans', [$this->plan()]);

        $plan = app(PlanCatalog::class)->assertSelectable('pro', User::factory()->make());

        $this->assertSame('pro', $plan['id']);
    }
}
