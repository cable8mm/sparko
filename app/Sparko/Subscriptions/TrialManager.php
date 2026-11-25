<?php

declare(strict_types=1);

namespace App\Sparko\Subscriptions;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class TrialManager
{
    /**
     * @param  array<string, mixed>  $plan
     */
    public function endsAt(BillableEntity $billable, array $plan): ?CarbonImmutable
    {
        $days = $plan['trial_days'] ?? config('plans.default_trial_days');

        if (! is_int($days) || $days <= 0) {
            return null;
        }

        $repeat = config('plans.repeat_trial');

        if (is_array($repeat) && ($repeat['enabled'] ?? false) === true) {
            $since = $repeat['since_last_subscription_days'] ?? null;

            if ($billable instanceof Model) {
                $query = SparkoSubscription::query()
                    ->where('billable_type', $billable->getMorphClass())
                    ->where('billable_id', (string) $billable->getKey());

                if (is_int($since)) {
                    $query->where('created_at', '>=', CarbonImmutable::now()->subDays($since));
                }

                if ($query->exists()) {
                    return null;
                }
            }
        }

        return CarbonImmutable::now()->addDays($days);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function requiresPaymentMethod(array $plan): bool
    {
        $value = $plan['trial_requires_payment_method'] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        return (bool) config('plans.trial_requires_payment_method');
    }
}
