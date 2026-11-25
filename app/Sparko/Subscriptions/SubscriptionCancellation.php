<?php

declare(strict_types=1);

namespace App\Sparko\Subscriptions;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SubscriptionCancellation
{
    public function cancel(BillableEntity $billable): SparkoSubscription
    {
        $subscription = $this->subscription($billable);

        if ($subscription->status === 'CANCELLED') {
            throw new DomainException('The subscription is already scheduled to end.');
        }

        $subscription->update(['status' => 'CANCELLED']);

        return $subscription->fresh();
    }

    public function resume(BillableEntity $billable): SparkoSubscription
    {
        $subscription = $this->subscription($billable);

        if ($subscription->status !== 'CANCELLED') {
            throw new DomainException('The subscription is not scheduled to end.');
        }

        $endsAt = $subscription->getRawOriginal('next_charge_at');

        if (! is_string($endsAt) || CarbonImmutable::parse($endsAt)->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw new DomainException('A cancellation can only be resumed before the period ends.');
        }

        $trialEndsAt = $subscription->getRawOriginal('trial_ends_at');
        $wasTrial = is_string($trialEndsAt) && CarbonImmutable::parse($trialEndsAt)->isAfter(CarbonImmutable::now());

        $subscription->update(['status' => $wasTrial ? 'TRIAL' : 'ACTIVE']);

        return $subscription->fresh();
    }

    private function subscription(BillableEntity $billable): SparkoSubscription
    {
        if (! $billable instanceof Model) {
            throw new InvalidArgumentException('A billable entity must be an Eloquent model.');
        }

        $subscription = SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->whereNotIn('status', ['ENDED', 'EXPIRED'])
            ->first();

        if ($subscription === null) {
            throw new DomainException('No active subscription exists for this billable entity.');
        }

        return $subscription;
    }
}
