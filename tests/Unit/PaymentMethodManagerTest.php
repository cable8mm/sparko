<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SparkoSubscription;
use App\Models\User;
use App\Sparko\PaymentMethods\BillingKeyInUse;
use App\Sparko\PaymentMethods\PaymentMethodManager;
use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\FakeBillingKeyGateway;
use Tests\TestCase;

class PaymentMethodManagerTest extends TestCase
{
    use RefreshDatabase;

    private FakeBillingKeyGateway $fakeGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGateway = new FakeBillingKeyGateway;
        $this->app->instance(BillingKeyGateway::class, $this->fakeGateway);
    }

    private function key(User $user, string $issueId, string $plain): BillingKey
    {
        return BillingKey::query()->create([
            'issue_id' => $issueId,
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'customer_id' => 'customer-1',
            'issue_name' => 'card',
            'billing_key' => Crypt::encryptString($plain),
            'status' => 'ISSUED',
        ]);
    }

    public function test_replacement_uses_the_new_key_and_deletes_the_old_one(): void
    {
        $user = User::factory()->create();
        $old = $this->key($user, 'bk-old', 'old-plain');
        SparkoSubscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'plan_id' => 'pro',
            'billing_key_issue_id' => $old->issue_id,
            'status' => 'TRIAL',
        ]);

        $new = app(PaymentMethodManager::class)->replace($user, 'new-plain', 'customer-1', 'new-card');

        $this->assertNotSame($old->issue_id, $new->issue_id);
        $this->assertSame('DELETED', $old->fresh()->status);
        $this->assertSame($new->issue_id, SparkoSubscription::query()->sole()->billing_key_issue_id);
        $this->assertContains('old-plain', $this->fakeGateway->deleted);
    }

    public function test_removal_is_blocked_while_a_non_ended_subscription_depends_on_the_key(): void
    {
        $user = User::factory()->create();
        $key = $this->key($user, 'bk-blocked', 'plain');
        SparkoSubscription::query()->create([
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'plan_id' => 'pro',
            'billing_key_issue_id' => $key->issue_id,
            'status' => 'TRIAL',
        ]);

        $this->expectException(BillingKeyInUse::class);

        app(PaymentMethodManager::class)->remove($user, $key->issue_id);
    }

    public function test_removal_deletes_a_key_with_no_active_dependency(): void
    {
        $user = User::factory()->create();
        $key = $this->key($user, 'bk-free', 'plain');

        app(PaymentMethodManager::class)->remove($user, $key->issue_id);

        $this->assertSame('DELETED', $key->fresh()->status);
        $this->assertContains('plain', $this->fakeGateway->deleted);
    }
}
