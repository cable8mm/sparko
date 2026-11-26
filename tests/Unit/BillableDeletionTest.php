<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Tests\Support\FakeBillingKeyGateway;
use Tests\TestCase;

class BillableDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function setupSubscription(User $user): BillingKey
    {
        $key = BillingKey::query()->create([
            'issue_id' => 'bk-delete',
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'customer_id' => 'customer-1',
            'issue_name' => 'card',
            'billing_key' => Crypt::encryptString('plain-key'),
            'status' => 'ISSUED',
        ]);

        SparkoSubscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'plan_id' => 'pro',
            'billing_interval' => 'monthly',
            'renewal_amount' => 10000,
            'seat_quantity' => 1,
            'credit_balance' => 5000,
            'billing_key_issue_id' => $key->issue_id,
            'next_charge_at' => CarbonImmutable::now()->addDays(10),
            'status' => 'ACTIVE',
        ]);

        return $key;
    }

    public function test_deletion_ends_the_subscription_without_a_refund(): void
    {
        $this->app->instance(BillingKeyGateway::class, new FakeBillingKeyGateway);
        $user = User::factory()->create();
        $key = $this->setupSubscription($user);

        $user->delete();

        $subscription = SparkoSubscription::query()->sole();
        $this->assertSame('ENDED', $subscription->status);
        $this->assertNull($subscription->next_charge_at);
        $this->assertSame(5000, $subscription->credit_balance);
        $this->assertSame('DELETED', $key->fresh()->status);
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_provider_failure_prevents_deletion_and_keeps_the_subscription(): void
    {
        $fake = new FakeBillingKeyGateway;
        $fake->failDeletes = true;
        $this->app->instance(BillingKeyGateway::class, $fake);
        $user = User::factory()->create();
        $key = $this->setupSubscription($user);

        try {
            $user->delete();
            $this->fail('Deletion should be prevented when the provider cannot end the schedule.');
        } catch (RuntimeException) {
            $this->assertSame(1, User::query()->count());
        }

        $subscription = SparkoSubscription::query()->sole();
        $this->assertSame('ACTIVE', $subscription->status);
        $this->assertNotNull($subscription->next_charge_at);
        $this->assertSame('ISSUED', $key->fresh()->status);
    }
}
