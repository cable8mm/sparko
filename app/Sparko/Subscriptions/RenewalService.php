<?php

declare(strict_types=1);

namespace App\Sparko\Subscriptions;

use App\Models\SparkoSubscription;
use App\Sparko\Plans\PlanPricing;
use Cable8mm\LaravelPortone\PaymentOperations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class RenewalService
{
    private const ENDED_STATUSES = ['ENDED', 'EXPIRED'];

    public function __construct(
        private readonly PlanPricing $pricing,
        private readonly PaymentOperations $payments,
    ) {}

    public function chargeDue(CarbonImmutable $now): int
    {
        $count = 0;

        $due = SparkoSubscription::query()
            ->whereNotIn('status', self::ENDED_STATUSES)
            ->whereNotNull('next_charge_at')
            ->where('next_charge_at', '<=', $now)
            ->get();

        foreach ($due as $subscription) {
            $period = (string) $subscription->getRawOriginal('next_charge_at');

            if ($subscription->last_charged_period === $period) {
                continue;
            }

            $billableClass = (string) $subscription->billable_type;

            if (! class_exists($billableClass)) {
                continue;
            }

            $billable = $billableClass::query()->find((int) $subscription->billable_id);

            if (! $billable instanceof Model) {
                continue;
            }

            $amount = (int) $subscription->renewal_amount - (int) $subscription->credit_balance;
            $credit = 0;

            if ($amount <= 0) {
                $credit = abs($amount);
                $amount = 0;
            }

            if ($amount > 0) {
                $this->payments->chargeBillingKey(
                    $billable,
                    (string) $subscription->billing_key_issue_id,
                    $amount,
                    'Subscription renewal',
                );
            }

            $next = CarbonImmutable::parse($period)
                ->addDays($this->pricing->periodDays((string) $subscription->billing_interval));

            $subscription->update([
                'last_charged_period' => $period,
                'next_charge_at' => $next,
                'credit_balance' => $credit,
            ]);

            $count++;
        }

        return $count;
    }
}
