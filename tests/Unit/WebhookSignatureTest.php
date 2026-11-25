<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cable8mm\LaravelPortone\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invalid_signature_is_rejected_before_any_effect(): void
    {
        config()->set('portone.webhook_secret', 'whsec_'.base64_encode('test-secret'));

        $body = json_encode([
            'type' => 'Transaction.Paid',
            'timestamp' => now()->toIso8601String(),
            'data' => ['paymentId' => 'p-test', 'storeId' => 'store-test', 'transactionId' => 't-test'],
        ]);

        $this->call('POST', route('portone.webhook'), [], [], [], [
            'HTTP_WEBHOOK_ID' => 'msg-invalid',
            'HTTP_WEBHOOK_SIGNATURE' => 'v1,invalid',
            'HTTP_WEBHOOK_TIMESTAMP' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);

        $this->assertSame(0, Webhook::query()->count());
    }
}
