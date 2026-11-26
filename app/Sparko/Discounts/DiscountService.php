<?php

declare(strict_types=1);

namespace App\Sparko\Discounts;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class DiscountService
{
    public function apply(BillableEntity $billable, string $code): SparkoSubscription
    {
        $subscription = $this->subscription($billable);
        $entry = $this->entry($code);

        $now = CarbonImmutable::now();
        $activeFrom = $entry['active_from'] ?? null;
        $activeUntil = $entry['active_until'] ?? null;

        if (is_string($activeFrom) && CarbonImmutable::parse($activeFrom)->isAfter($now)) {
            throw new InvalidDiscount('The promotion code is not active yet.');
        }

        if (is_string($activeUntil) && CarbonImmutable::parse($activeUntil)->isBefore($now)) {
            throw new InvalidDiscount('The promotion code has expired.');
        }

        $eligibility = $entry['eligibility'] ?? [];
        $planIds = is_array($eligibility) && isset($eligibility['plan_ids']) && is_array($eligibility['plan_ids'])
            ? $eligibility['plan_ids']
            : [];

        if ($planIds !== [] && ! in_array((string) $subscription->plan_id, $planIds, true)) {
            throw new InvalidDiscount('The promotion code does not apply to this plan.');
        }

        $duration = (string) ($entry['duration'] ?? '');

        if (! in_array($duration, ['once', 'charges', 'lifetime'], true)) {
            throw new InvalidDiscount('The promotion code has an unsupported duration.');
        }

        $subscription->update([
            'discount_code' => $code,
            'discount_type' => (string) ($entry['type'] ?? ''),
            'discount_value' => (int) ($entry['value'] ?? 0),
            'discount_duration' => $duration,
            'discount_remaining_charges' => $duration === 'charges' ? (int) ($entry['charges'] ?? 0) : null,
            'discount_expires_at' => is_string($activeUntil) ? CarbonImmutable::parse($activeUntil) : null,
        ]);

        return $subscription->fresh();
    }

    public function resolve(int $amount, SparkoSubscription $subscription): int
    {
        if (! is_string($subscription->discount_code) || $subscription->discount_code === '') {
            return $amount;
        }

        if ($subscription->discount_remaining_charges === 0) {
            return $amount;
        }

        $expiresAt = $subscription->getRawOriginal('discount_expires_at');

        if (is_string($expiresAt) && CarbonImmutable::parse($expiresAt)->isBefore(CarbonImmutable::now())) {
            return $amount;
        }

        $value = (int) $subscription->discount_value;

        if ($subscription->discount_type === 'fixed') {
            return max(0, $amount - $value);
        }

        if ($subscription->discount_type === 'percentage') {
            return max(0, $amount - (int) round($amount * $value / 100));
        }

        return $amount;
    }

    public function consume(SparkoSubscription $subscription): void
    {
        if ($subscription->discount_duration === 'once') {
            $this->clear($subscription);

            return;
        }

        if ($subscription->discount_duration === 'charges') {
            $remaining = (int) $subscription->discount_remaining_charges - 1;

            if ($remaining <= 0) {
                $this->clear($subscription);

                return;
            }

            $subscription->update(['discount_remaining_charges' => $remaining]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $code): array
    {
        $codes = config('discounts.codes');

        if (! is_array($codes)) {
            throw new InvalidDiscount('No promotion codes are configured.');
        }

        foreach ($codes as $entry) {
            if (is_array($entry) && ($entry['code'] ?? null) === $code) {
                return $entry;
            }
        }

        throw new InvalidDiscount('The promotion code does not exist.');
    }

    private function clear(SparkoSubscription $subscription): void
    {
        $subscription->update([
            'discount_code' => null,
            'discount_type' => null,
            'discount_value' => null,
            'discount_duration' => null,
            'discount_remaining_charges' => null,
            'discount_expires_at' => null,
        ]);
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
            throw new InvalidDiscount('No active subscription exists for this billable entity.');
        }

        return $subscription;
    }
}
