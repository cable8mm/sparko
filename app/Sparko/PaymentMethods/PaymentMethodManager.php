<?php

declare(strict_types=1);

namespace App\Sparko\PaymentMethods;

use App\Models\SparkoSubscription;
use App\Sparko\Contracts\BillableEntity;
use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\BillingKeyRegistry;
use Cable8mm\LaravelPortone\Models\BillingKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PaymentMethodManager
{
    private const ENDED_STATUSES = ['ENDED', 'EXPIRED'];

    public function __construct(
        private readonly BillingKeyGateway $gateway,
        private readonly BillingKeyRegistry $registry,
    ) {}

    public function replace(BillableEntity $billable, string $billingKey, string $customerId, string $issueName): BillingKey
    {
        $billable = $this->model($billable);

        $info = $this->gateway->billingKeyInfo($billingKey);

        if ($info->status !== 'ISSUED') {
            throw ValidationException::withMessages(['billing_key' => 'PortOne did not confirm the billing key.']);
        }

        if ($info->customerId !== null && $info->customerId !== $customerId) {
            throw ValidationException::withMessages(['customer_id' => 'The customer does not match the confirmed billing key.']);
        }

        $previousIssueId = SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->whereNotIn('status', self::ENDED_STATUSES)
            ->value('billing_key_issue_id');

        $previous = is_string($previousIssueId)
            ? BillingKey::query()
                ->where('billable_type', $billable->getMorphClass())
                ->where('billable_id', (string) $billable->getKey())
                ->where('issue_id', $previousIssueId)
                ->first()
            : null;

        $new = $this->registry->createPending($billable, $customerId, $issueName);
        $this->registry->markIssued($new, $billingKey, CarbonImmutable::now());

        if ($previous instanceof BillingKey && $previous->status !== 'DELETED') {
            $plain = $this->registry->plainKey($previous);

            if ($plain !== null) {
                $this->gateway->deleteBillingKey($plain, 'replaced');
            }

            $this->registry->markDeleted($previous, CarbonImmutable::now());
        }

        SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->whereNotIn('status', self::ENDED_STATUSES)
            ->update(['billing_key_issue_id' => $new->issue_id]);

        return $new;
    }

    public function remove(BillableEntity $billable, string $issueId): void
    {
        $billable = $this->model($billable);

        $depends = SparkoSubscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->where('billing_key_issue_id', $issueId)
            ->whereNotIn('status', self::ENDED_STATUSES)
            ->exists();

        if ($depends) {
            throw new BillingKeyInUse('A non-ended subscription depends on this payment method.');
        }

        $record = BillingKey::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->where('issue_id', $issueId)
            ->first();

        if ($record === null || $record->status === 'DELETED') {
            return;
        }

        $plain = $this->registry->plainKey($record);

        if ($plain !== null) {
            $this->gateway->deleteBillingKey($plain, 'removed');
        }

        $this->registry->markDeleted($record, CarbonImmutable::now());
    }

    private function model(BillableEntity $billable): Model
    {
        if (! $billable instanceof Model) {
            throw new InvalidArgumentException('A billable entity must be an Eloquent model.');
        }

        return $billable;
    }
}
