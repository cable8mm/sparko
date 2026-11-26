<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SparkoSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('sparko.return_url', '/after-billing');
        config()->set('plans.plans', [[
            'id' => 'pro',
            'name' => 'Pro',
            'description' => 'Pro 요금제',
            'prices' => ['monthly' => 10000],
            'trial_days' => 5,
            'features' => ['included' => ['리포트'], 'unavailable' => ['좌석']],
            'options' => [],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
        ]]);
    }

    public function test_the_portal_uses_korean_labels_currency_and_dates(): void
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
            'next_charge_at' => CarbonImmutable::parse('2026-12-26 00:00:00'),
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user)->get(route('sparko.portal', $user))
            ->assertOk()
            ->assertSee('구독 취소')
            ->assertSee('₩10,000')
            ->assertSee('2026년 12월 26일')
            ->assertSee('5일 무료 체험')
            ->assertSee('리포트')
            ->assertSee('좌석');
    }

    public function test_the_portal_returns_to_the_configured_destination(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('sparko.portal', $user))
            ->assertOk()
            ->assertSee('href="/after-billing" data-return', false);
    }

    public function test_the_portal_uses_only_its_own_bundled_entry(): void
    {
        $view = (string) file_get_contents(resource_path('views/sparko/portal.blade.php'));
        $viteConfig = (string) file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString('resources/js/portal.jsx', $view);
        $this->assertStringNotContainsString('resources/js/app.js', $view);
        $this->assertStringContainsString('resources/js/portal.jsx', $viteConfig);
    }
}
