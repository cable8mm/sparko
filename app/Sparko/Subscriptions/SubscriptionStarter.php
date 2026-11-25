<?php

declare(strict_types=1);

namespace App\Sparko\Subscriptions;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use App\Sparko\Plans\PlanCatalog;
use App\Sparko\Plans\PlanPricing;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SubscriptionStarter
{
    public function __construct(
        private readonly TrialManager $trials,
        private readonly PlanPricing $pricing,
    ) {}

    public function start(
        BillableEntity $billable,
        PlanCatalog $catalog,
        string $planId,
        bool $termsAccepted,
        ?string $billingKeyIssueId,
    ): SparkoSubscription {
        if (! $billable instanceof Model) {
            throw new InvalidArgumentException('A billable entity must be an Eloquent model.');
        }

        $plan = $catalog->assertSelectable($planId, $billable);

        if (config('sparko.require_terms') === true && ! $termsAccepted) {
            throw new TermsNotAccepted('The configured terms must be accepted before starting a subscription.');
        }

        $trialEndsAt = $this->trials->endsAt($billable, $plan);
        $requiresKey = $trialEndsAt === null || $this->trials->requiresPaymentMethod($plan);

        if ($requiresKey && ($billingKeyIssueId === null || $billingKeyIssueId === '')) {
            throw new BillingKeyRequired('A confirmed billing key is required before the subscription can start.');
        }

        if ($billingKeyIssueId !== null && $billingKeyIssueId !== '') {
            $key = BillingKey::query()
                ->where('billable_type', $billable->getMorphClass())
                ->where('billable_id', (string) $billable->getKey())
                ->where('issue_id', $billingKeyIssueId)
                ->where('status', 'ISSUED')
                ->first();

            if ($key === null) {
                throw new BillingKeyRequired('The billing key is not confirmed for this billable entity.');
            }
        }

        return SparkoSubscription::query()->create([
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => (string) $billable->getKey(),
            'plan_id' => $planId,
            'billing_interval' => $this->pricing->interval($plan),
            'renewal_amount' => $this->pricing->amount($plan),
            'seat_quantity' => 1,
            'credit_balance' => 0,
            'billing_key_issue_id' => $billingKeyIssueId,
            'trial_ends_at' => $trialEndsAt,
            'next_charge_at' => $trialEndsAt,
            'terms_accepted_at' => $termsAccepted ? CarbonImmutable::now() : null,
            'status' => $trialEndsAt === null ? 'ACTIVE' : 'TRIAL',
        ]);
    }
}
