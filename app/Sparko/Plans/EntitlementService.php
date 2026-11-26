<?php

declare(strict_types=1);

namespace App\Sparko\Plans;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EntitlementService
{
    public function __construct(private readonly PlanCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function optionsFor(BillableEntity $billable): array
    {
        $subscription = $this->subscription($billable);

        if ($subscription === null) {
            return [];
        }

        $plans = $this->catalog->all();
        $planId = (string) $subscription->plan_id;

        if (! isset($plans[$planId])) {
            return [];
        }

        $options = $plans[$planId]['options'] ?? [];

        return is_array($options) ? $options : [];
    }

    public function isActive(BillableEntity $billable): bool
    {
        $subscription = $this->subscription($billable);

        if ($subscription === null) {
            return false;
        }

        if (in_array($subscription->status, ['ACTIVE', 'TRIAL'], true)) {
            return true;
        }

        if ($subscription->status !== 'CANCELLED') {
            return false;
        }

        $endsAt = $subscription->getRawOriginal('next_charge_at');

        return is_string($endsAt)
            && CarbonImmutable::parse($endsAt)->isAfter(CarbonImmutable::now());
    }

    private function subscription(BillableEntity $billable): ?SparkoSubscription
    {
        if (! $billable instanceof Model) {
            throw new InvalidArgumentException('A billable entity must be an Eloquent model.');
        }

        return SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->whereNotIn('status', ['ENDED', 'EXPIRED'])
            ->first();
    }
}
