<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use App\Sparko\PaymentMethods\BillingKeyInUse;
use App\Sparko\PaymentMethods\PaymentMethodManager;
use App\Sparko\Plans\PlanCatalog;
use App\Sparko\Subscriptions\PlanSwitchService;
use App\Sparko\Subscriptions\SubscriptionStarter;
use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\BillingKeyRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PortalController extends Controller
{
    public function show(mixed $billable, PlanCatalog $catalog): View
    {
        $billable = $this->billable($billable);

        $subscription = SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->whereNotIn('status', ['ENDED', 'EXPIRED'])
            ->first();

        $currentPlanId = $subscription === null ? null : (string) $subscription->plan_id;

        return view('sparko.portal', [
            'billable' => $billable,
            'plans' => $catalog->forPortal($billable, $currentPlanId),
            'currentPlanId' => $currentPlanId,
            'subscription' => $subscription,
        ]);
    }

    public function enrollPaymentMethod(
        Request $request,
        mixed $billable,
        BillingKeyGateway $gateway,
        BillingKeyRegistry $registry,
    ): JsonResponse {
        $billable = $this->billable($billable);

        $data = $request->validate([
            'billing_key' => ['required', 'string'],
            'customer_id' => ['required', 'string'],
            'issue_name' => ['required', 'string'],
        ]);

        $info = $gateway->billingKeyInfo($data['billing_key']);

        if ($info->status !== 'ISSUED') {
            throw ValidationException::withMessages(['billing_key' => 'PortOne did not confirm the billing key.']);
        }

        if ($info->customerId !== null && $info->customerId !== $data['customer_id']) {
            throw ValidationException::withMessages(['customer_id' => 'The customer does not match the confirmed billing key.']);
        }

        $record = $registry->createPending($billable, $data['customer_id'], $data['issue_name']);
        $registry->markIssued($record, $data['billing_key'], CarbonImmutable::now());

        return response()->json([
            'issue_id' => $record->issue_id,
            'customer_id' => $record->customer_id,
            'issue_name' => $record->issue_name,
            'status' => 'ISSUED',
        ], 201);
    }

    public function replacePaymentMethod(Request $request, mixed $billable, PaymentMethodManager $manager): JsonResponse
    {
        $billable = $this->billable($billable);

        $data = $request->validate([
            'billing_key' => ['required', 'string'],
            'customer_id' => ['required', 'string'],
            'issue_name' => ['required', 'string'],
        ]);

        $record = $manager->replace(
            $billable,
            $data['billing_key'],
            $data['customer_id'],
            $data['issue_name'],
        );

        return response()->json([
            'issue_id' => $record->issue_id,
            'customer_id' => $record->customer_id,
            'issue_name' => $record->issue_name,
            'status' => 'ISSUED',
        ]);
    }

    public function removePaymentMethod(Request $request, mixed $billable, PaymentMethodManager $manager): JsonResponse
    {
        $billable = $this->billable($billable);

        $data = $request->validate([
            'issue_id' => ['required', 'string'],
        ]);

        try {
            $manager->remove($billable, $data['issue_id']);
        } catch (BillingKeyInUse $exception) {
            throw ValidationException::withMessages(['issue_id' => $exception->getMessage()]);
        }

        return response()->json(['status' => 'DELETED']);
    }

    public function switchPlan(Request $request, mixed $billable, PlanSwitchService $service): JsonResponse
    {
        $billable = $this->billable($billable);

        $data = $request->validate([
            'plan_id' => ['required', 'string'],
            'seat_quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $subscription = $service->switch(
            $billable,
            $data['plan_id'],
            isset($data['seat_quantity']) ? (int) $data['seat_quantity'] : null,
        );

        return response()->json([
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'seat_quantity' => $subscription->seat_quantity,
            'renewal_amount' => $subscription->renewal_amount,
            'credit_balance' => $subscription->credit_balance,
        ]);
    }

    public function startSubscription(
        Request $request,
        mixed $billable,
        PlanCatalog $catalog,
        SubscriptionStarter $starter,
    ): JsonResponse {
        $billable = $this->billable($billable);

        $data = $request->validate([
            'plan_id' => ['required', 'string'],
            'terms_accepted' => ['sometimes', 'boolean'],
            'billing_key_issue_id' => ['nullable', 'string'],
        ]);

        $subscription = $starter->start(
            $billable,
            $catalog,
            $data['plan_id'],
            (bool) ($data['terms_accepted'] ?? false),
            $data['billing_key_issue_id'] ?? null,
        );

        $trialEndsAt = $subscription->getRawOriginal('trial_ends_at');

        return response()->json([
            'id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'trial_ends_at' => is_string($trialEndsAt) ? CarbonImmutable::parse($trialEndsAt)->toIso8601String() : null,
        ], 201);
    }

    private function billable(mixed $billable): Model&BillableEntity
    {
        if (! $billable instanceof Model || ! $billable instanceof BillableEntity) {
            throw new InvalidArgumentException('The resolved billable must be an Eloquent model implementing BillableEntity.');
        }

        return $billable;
    }
}
