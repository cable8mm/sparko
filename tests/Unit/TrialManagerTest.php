<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\Subscriptions\TrialManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_trial_ends_five_days_from_now(): void
    {
        $ends = app(TrialManager::class)->endsAt(User::factory()->make(), ['trial_days' => null]);

        $this->assertNotNull($ends);
        $this->assertSame(CarbonImmutable::now()->addDays(5)->toDateString(), $ends->toDateString());
    }

    public function test_plan_override_controls_trial_length(): void
    {
        $ends = app(TrialManager::class)->endsAt(User::factory()->make(), ['trial_days' => 14]);

        $this->assertNotNull($ends);
        $this->assertSame(CarbonImmutable::now()->addDays(14)->toDateString(), $ends->toDateString());
    }

    public function test_repeat_trial_restriction_removes_the_trial(): void
    {
        config()->set('plans.repeat_trial', ['enabled' => true, 'since_last_subscription_days' => 30]);
        $user = User::factory()->create();

        SparkoSubscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'plan_id' => 'pro',
            'billing_key_issue_id' => 'bk-test',
            'status' => 'ACTIVE',
        ]);

        $this->assertNull(app(TrialManager::class)->endsAt($user, ['trial_days' => 5]));
    }

    public function test_payment_method_requirement_uses_global_or_plan_override(): void
    {
        config()->set('plans.trial_requires_payment_method', false);
        $trials = app(TrialManager::class);

        $this->assertFalse($trials->requiresPaymentMethod([]));
        $this->assertTrue($trials->requiresPaymentMethod(['trial_requires_payment_method' => true]));
    }
}
