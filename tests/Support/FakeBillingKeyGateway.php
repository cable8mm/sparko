<?php

declare(strict_types=1);

namespace Tests\Support;

use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\GatewayBillingKey;

class FakeBillingKeyGateway implements BillingKeyGateway
{
    /** @var list<string> */
    public array $deleted = [];

    public bool $failDeletes = false;

    public function assertConfigured(): void {}

    public function billingKeyInfo(#[\SensitiveParameter] string $billingKey): GatewayBillingKey
    {
        return new GatewayBillingKey('ISSUED', 'customer-1', null);
    }

    public function deleteBillingKey(#[\SensitiveParameter] string $billingKey, string $reason): void
    {
        if ($this->failDeletes) {
            throw new \RuntimeException('provider unavailable');
        }

        $this->deleted[] = $billingKey;
    }

    public function payWithBillingKey(string $paymentId, #[\SensitiveParameter] string $billingKey, string $orderName, int $amount): void {}
}
