<?php

declare(strict_types=1);

namespace Tests\Support;

use Cable8mm\LaravelPortone\BillingKeyGateway;
use Cable8mm\LaravelPortone\GatewayBillingKey;

class FakeBillingKeyGateway implements BillingKeyGateway
{
    public function assertConfigured(): void {}

    public function billingKeyInfo(#[\SensitiveParameter] string $billingKey): GatewayBillingKey
    {
        return new GatewayBillingKey('ISSUED', 'customer-1', null);
    }

    public function deleteBillingKey(#[\SensitiveParameter] string $billingKey, string $reason): void {}

    public function payWithBillingKey(string $paymentId, #[\SensitiveParameter] string $billingKey, string $orderName, int $amount): void {}
}
