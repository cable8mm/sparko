<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\BillingKeyRegistry;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class EndSubscriptionBeforeBillableDeletion
{
    public function __construct(
        private readonly BillingKeyGateway $gateway,
        private readonly BillingKeyRegistry $registry,
    ) {}

    public function handle(Model $billable): void
    {
        if (! $billable instanceof BillableEntity) {
            return;
        }

        $subscription = SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->whereNotIn('status', ['ENDED', 'EXPIRED'])
            ->first();

        if ($subscription === null) {
            return;
        }

        $key = BillingKey::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->where('issue_id', (string) $subscription->billing_key_issue_id)
            ->first();

        if ($key !== null && $key->status !== 'DELETED') {
            $plain = $this->registry->plainKey($key);

            if ($plain !== null) {
                $this->gateway->deleteBillingKey($plain, 'billable entity deleted');
            }

            $this->registry->markDeleted($key, CarbonImmutable::now());
        }

        $subscription->update([
            'status' => 'ENDED',
            'next_charge_at' => null,
        ]);
    }
}
