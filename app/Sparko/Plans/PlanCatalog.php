<?php

declare(strict_types=1);

namespace App\Sparko\Plans;

use App\Sparko\Contracts\BillableEntity;

class PlanCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $configured = config('plans.plans');

        if (! is_array($configured)) {
            throw new InvalidPlanConfiguration('plans.plans must be an array.');
        }

        $plans = [];

        foreach ($configured as $plan) {
            $this->validate($plan);

            /** @var array{id: string} $plan */
            $plans[$plan['id']] = $plan;
        }

        return $plans;
    }

    public function validate(mixed $plan): void
    {
        if (! is_array($plan)) {
            throw new InvalidPlanConfiguration('A plan must be an array.');
        }

        foreach (['id', 'name', 'description', 'prices', 'trial_days', 'features', 'options', 'eligibility', 'incentive', 'archived', 'seat_pricing'] as $key) {
            if (! array_key_exists($key, $plan)) {
                throw new InvalidPlanConfiguration(sprintf('A plan is missing "%s".', $key));
            }
        }

        if (! is_string($plan['id']) || $plan['id'] === '') {
            throw new InvalidPlanConfiguration('A plan id must be a non-empty string.');
        }

        if (! is_string($plan['name']) || ! is_string($plan['description'])) {
            throw new InvalidPlanConfiguration('A plan name and description must be strings.');
        }

        $this->validatePrices($plan['prices']);
        $this->validateTrialDays($plan['trial_days']);

        if (array_key_exists('trial_requires_payment_method', $plan) && ! is_bool($plan['trial_requires_payment_method'])) {
            throw new InvalidPlanConfiguration('A plan trial_requires_payment_method must be a boolean.');
        }

        $this->validateFeatures($plan['features']);

        if (! is_array($plan['options']) || ! is_array($plan['eligibility'])) {
            throw new InvalidPlanConfiguration('A plan options and eligibility must be arrays.');
        }

        if ($plan['incentive'] !== null && ! is_string($plan['incentive'])) {
            throw new InvalidPlanConfiguration('A plan incentive must be a string or null.');
        }

        if (! is_bool($plan['archived'])) {
            throw new InvalidPlanConfiguration('A plan archived flag must be a boolean.');
        }

        $this->validateSeatPricing($plan['seat_pricing']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPortal(BillableEntity $billable, ?string $currentPlanId): array
    {
        $entries = [];

        foreach ($this->all() as $id => $plan) {
            $entries[] = [
                ...$plan,
                'is_current' => $id === $currentPlanId,
                'selectable' => ! $plan['archived'] && $this->isEligible($plan, $billable),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    public function assertSelectable(string $planId, BillableEntity $billable): array
    {
        $plans = $this->all();

        if (! isset($plans[$planId])) {
            throw new InvalidPlanConfiguration(sprintf('Plan "%s" is not configured.', $planId));
        }

        $plan = $plans[$planId];

        if ($plan['archived'] === true || ! $this->isEligible($plan, $billable)) {
            throw new IneligiblePlan(sprintf('Plan "%s" is not selectable.', $planId));
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function isEligible(array $plan, BillableEntity $billable): bool
    {
        $eligibility = $plan['eligibility'];
        $types = is_array($eligibility) && isset($eligibility['billable_types']) && is_array($eligibility['billable_types'])
            ? $eligibility['billable_types']
            : [];

        if ($types === []) {
            return true;
        }

        foreach ($types as $type) {
            if (is_string($type) && $billable instanceof $type) {
                return true;
            }
        }

        return false;
    }

    private function validatePrices(mixed $prices): void
    {
        if (! is_array($prices)) {
            throw new InvalidPlanConfiguration('A plan prices must be an array.');
        }

        $found = false;

        foreach (['monthly', 'yearly'] as $interval) {
            if (! array_key_exists($interval, $prices)) {
                continue;
            }

            if (! is_int($prices[$interval]) || $prices[$interval] <= 0) {
                throw new InvalidPlanConfiguration(sprintf('A plan %s price must be a positive integer.', $interval));
            }

            $found = true;
        }

        if (! $found) {
            throw new InvalidPlanConfiguration('A plan must have a monthly or yearly price.');
        }
    }

    private function validateTrialDays(mixed $trialDays): void
    {
        if ($trialDays !== null && (! is_int($trialDays) || $trialDays < 0)) {
            throw new InvalidPlanConfiguration('A plan trial_days must be null or a non-negative integer.');
        }
    }

    private function validateFeatures(mixed $features): void
    {
        if (! is_array($features) || ! isset($features['included'], $features['unavailable'])
            || ! is_array($features['included']) || ! is_array($features['unavailable'])) {
            throw new InvalidPlanConfiguration('A plan features must contain included and unavailable arrays.');
        }
    }

    private function validateSeatPricing(mixed $seatPricing): void
    {
        if (! is_array($seatPricing) || ! isset($seatPricing['enabled']) || ! is_bool($seatPricing['enabled'])) {
            throw new InvalidPlanConfiguration('A plan seat_pricing must contain an enabled flag.');
        }

        if ($seatPricing['enabled'] && (! isset($seatPricing['unit_price']) || ! is_int($seatPricing['unit_price']) || $seatPricing['unit_price'] <= 0)) {
            throw new InvalidPlanConfiguration('A plan seat_pricing.unit_price must be a positive integer when enabled.');
        }
    }
}
