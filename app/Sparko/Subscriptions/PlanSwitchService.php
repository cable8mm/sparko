<?php

declare(strict_types=1);

namespace App\Sparko\Subscriptions;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use App\Sparko\Plans\PlanCatalog;
use App\Sparko\Plans\PlanPricing;
use Cable8mm\LaravelPortone\PaymentOperations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PlanSwitchService
{
    private const ENDED_STATUSES = ['ENDED', 'EXPIRED'];

    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly PlanPricing $pricing,
        private readonly PaymentOperations $payments,
    ) {}

    public function switch(BillableEntity $billable, string $planId, ?int $seatQuantity): SparkoSubscription
    {
        if (! $billable instanceof Model) {
            throw new InvalidArgumentException('A billable entity must be an Eloquent model.');
        }

        $subscription = SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->whereNotIn('status', self::ENDED_STATUSES)
            ->first();

        if ($subscription === null) {
            throw new InvalidArgumentException('No active subscription exists for this billable entity.');
        }

        $plans = $this->catalog->all();
        $oldPlanId = (string) $subscription->plan_id;

        if (! isset($plans[$oldPlanId])) {
            throw new InvalidArgumentException('The current plan is not configured.');
        }

        $newPlan = $this->catalog->assertSelectable($planId, $billable);
        $oldInterval = $this->pricing->interval($plans[$oldPlanId]);
        $newInterval = $this->pricing->interval($newPlan);
        $seats = max(1, $seatQuantity ?? (int) $subscription->seat_quantity);

        $rawEndsAt = $subscription->getRawOriginal('next_charge_at');
        $remainingDays = is_string($rawEndsAt) && $rawEndsAt !== ''
            ? max(0, (int) CarbonImmutable::now()->diffInDays(CarbonImmutable::parse($rawEndsAt)))
            : $this->pricing->periodDays($oldInterval);

        $oldProrated = $this->pricing->prorated((int) $subscription->renewal_amount, $oldInterval, $remainingDays);
        $newAmount = $this->pricing->amount($newPlan, $seats);
        $newProrated = $this->pricing->prorated($newAmount, $newInterval, $remainingDays);
        $difference = $newProrated - $oldProrated;

        if ($difference > 0) {
            $this->payments->chargeBillingKey(
                $billable,
                (string) $subscription->billing_key_issue_id,
                $difference,
                'Plan adjustment',
            );
        }

        $subscription->update([
            'plan_id' => $planId,
            'billing_interval' => $newInterval,
            'renewal_amount' => $newAmount,
            'seat_quantity' => $seats,
            'credit_balance' => $difference < 0
                ? (int) $subscription->credit_balance + abs($difference)
                : (int) $subscription->credit_balance,
        ]);

        return $subscription->fresh();
    }
}
