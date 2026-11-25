<?php

declare(strict_types=1);

namespace App\Sparko\Plans;

class PlanPricing
{
    /**
     * @param  array<string, mixed>  $plan
     */
    public function interval(array $plan): string
    {
        $prices = $plan['prices'];

        return is_array($prices) && isset($prices['monthly']) ? 'monthly' : 'yearly';
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function amount(array $plan, int $seats = 1): int
    {
        $interval = $this->interval($plan);
        $prices = $plan['prices'];
        $amount = is_array($prices) && isset($prices[$interval]) && is_int($prices[$interval]) ? $prices[$interval] : 0;

        $seatPricing = $plan['seat_pricing'] ?? [];

        if (is_array($seatPricing) && ($seatPricing['enabled'] ?? false) === true
            && isset($seatPricing['unit_price']) && is_int($seatPricing['unit_price'])) {
            return $seatPricing['unit_price'] * max(1, $seats);
        }

        return $amount;
    }

    public function periodDays(string $interval): int
    {
        return $interval === 'yearly' ? 365 : 30;
    }

    /**
     * Prorate an amount over the remaining days of a billing period.
     */
    public function prorated(int $amount, string $interval, int $remainingDays): int
    {
        $periodDays = $this->periodDays($interval);
        $days = max(0, min($remainingDays, $periodDays));

        return (int) round($amount * $days / $periodDays);
    }
}
