<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Sparko\Plans\InvalidPlanConfiguration;
use App\Sparko\Plans\PlanCatalog;
use App\Sparko\Subscriptions\BillingKeyRequired;
use App\Sparko\Subscriptions\SubscriptionStarter;
use App\Sparko\Subscriptions\TermsNotAccepted;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStarterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function plan(array $overrides = []): array
    {
        return [
            'id' => 'pro',
            'name' => 'Pro',
            'description' => 'Pro plan',
            'prices' => ['monthly' => 10000],
            'trial_days' => 5,
            'features' => ['included' => [], 'unavailable' => []],
            'options' => [],
            'eligibility' => [],
            'incentive' => null,
            'archived' => false,
            'seat_pricing' => ['enabled' => false],
            ...$overrides,
        ];
    }

    private function confirmedKey(User $user): string
    {
        return BillingKey::query()->create([
            'issue_id' => 'bk'.\bin2hex(\random_bytes(16)),
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'customer_id' => 'customer-1',
            'issue_name' => 'card',
            'billing_key' => 'encrypted',
            'status' => 'ISSUED',
        ])->issue_id;
    }

    public function test_starts_a_trial_with_a_confirmed_billing_key(): void
    {
        config()->set('plans.plans', [$this->plan()]);
        config()->set('plans.trial_requires_payment_method', true);
        $user = User::factory()->create();
        $issueId = $this->confirmedKey($user);

        $subscription = app(SubscriptionStarter::class)->start($user, app(PlanCatalog::class), 'pro', false, $issueId);

        $this->assertSame('TRIAL', $subscription->status);
        $this->assertSame('pro', $subscription->plan_id);
        $this->assertNotNull($subscription->trial_ends_at);
    }

    public function test_terms_are_required_when_configured(): void
    {
        config()->set('plans.plans', [$this->plan()]);
        config()->set('sparko.require_terms', true);
        $user = User::factory()->create();

        $this->expectException(TermsNotAccepted::class);

        app(SubscriptionStarter::class)->start($user, app(PlanCatalog::class), 'pro', false, null);
    }

    public function test_a_billing_key_is_required_when_trial_collects_it_up_front(): void
    {
        config()->set('plans.plans', [$this->plan()]);
        config()->set('plans.trial_requires_payment_method', true);
        $user = User::factory()->create();

        $this->expectException(BillingKeyRequired::class);

        app(SubscriptionStarter::class)->start($user, app(PlanCatalog::class), 'pro', false, null);
    }

    public function test_an_invalid_plan_cannot_start_a_subscription(): void
    {
        config()->set('plans.plans', [$this->plan()]);
        $user = User::factory()->create();

        $this->expectException(InvalidPlanConfiguration::class);

        app(SubscriptionStarter::class)->start($user, app(PlanCatalog::class), 'missing', false, null);
    }

    public function test_a_subscription_without_trial_starts_active(): void
    {
        config()->set('plans.plans', [$this->plan(['trial_days' => 0])]);
        $user = User::factory()->create();
        $issueId = $this->confirmedKey($user);

        $subscription = app(SubscriptionStarter::class)->start($user, app(PlanCatalog::class), 'pro', false, $issueId);

        $this->assertSame('ACTIVE', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }
}
