<?php

declare(strict_types=1);

namespace App\Sparko\Subscriptions;

use App\Mail\FailedRenewalNotice;
use App\Models\SparkoSubscription;
use App\Sparko\Discounts\DiscountService;
use App\Sparko\Plans\PlanPricing;
use Cable8mm\LaravelPortone\PaymentOperations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RenewalService
{
    private const ENDED_STATUSES = ['ENDED', 'EXPIRED'];

    public function __construct(
        private readonly PlanPricing $pricing,
        private readonly PaymentOperations $payments,
        private readonly DiscountService $discounts,
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
            if ($subscription->status === 'CANCELLED') {
                $subscription->update([
                    'status' => 'ENDED',
                    'next_charge_at' => null,
                ]);

                $count++;

                continue;
            }

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

            $amount = $this->discounts->resolve(
                (int) $subscription->renewal_amount - (int) $subscription->credit_balance,
                $subscription,
            );
            $credit = 0;

            if ($amount <= 0) {
                $credit = abs($amount);
                $amount = 0;
            }

            if ($amount > 0) {
                try {
                    $this->payments->chargeBillingKey(
                        $billable,
                        (string) $subscription->billing_key_issue_id,
                        $amount,
                        'Subscription renewal',
                    );
                } catch (Throwable) {
                    $this->scheduleRetry($subscription, $billable, $now);

                    $count++;

                    continue;
                }
            }

            $this->discounts->consume($subscription);

            $next = CarbonImmutable::parse($period)
                ->addDays($this->pricing->periodDays((string) $subscription->billing_interval));

            $subscription->update([
                'last_charged_period' => $period,
                'next_charge_at' => $next,
                'credit_balance' => $credit,
                'failed_attempts' => 0,
            ]);

            $count++;
        }

        return $count;
    }

    private function scheduleRetry(SparkoSubscription $subscription, Model $billable, CarbonImmutable $now): void
    {
        $attempts = (int) $subscription->failed_attempts + 1;
        $days = $this->retryDays();
        $index = $attempts - 1;

        if (! isset($days[$index])) {
            $subscription->update([
                'status' => 'EXPIRED',
                'next_charge_at' => null,
                'failed_attempts' => $attempts,
            ]);

            $this->notify($billable);

            return;
        }

        $subscription->update([
            'next_charge_at' => $now->addDays($days[$index]),
            'failed_attempts' => $attempts,
        ]);

        $this->notify($billable);
    }

    /**
     * @return list<int>
     */
    private function retryDays(): array
    {
        $configured = config('portone.subscription_retry_after');

        if (is_array($configured)) {
            return array_values(array_map('intval', $configured));
        }

        if (! is_string($configured)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $configured)), fn (int $day): bool => $day > 0));
    }

    private function notify(Model $billable): void
    {
        if (config('sparko.notify_failed_renewal') !== true) {
            return;
        }

        $email = data_get($billable, 'email');

        if (! is_string($email) || $email === '') {
            return;
        }

        Mail::to($email)->send(new FailedRenewalNotice($email));
    }
}
