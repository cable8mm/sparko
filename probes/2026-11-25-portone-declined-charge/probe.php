<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Cable8mm\LaravelPortone\BillingKeyGateway;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$secret = (string) config('portone.api_secret');
$channel = (string) config('sparko.e2e_channel_key');

if ($secret === '' || $channel === '') {
    throw new RuntimeException('PORTONE_API_SECRET and PORTONE_E2E_CHANNEL_KEY are required.');
}

$headers = ['Authorization' => 'PortOne '.$secret, 'Accept' => 'application/json'];
$customerId = 'sparko-decline-'.bin2hex(random_bytes(6));

$issued = Http::withHeaders($headers)->post('https://api.portone.io/billing-keys', [
    'channelKey' => $channel,
    'customer' => [
        'id' => $customerId,
        'name' => ['full' => '포트원'],
        'phoneNumber' => '010-0000-1234',
        'email' => 'test@portone.io',
    ],
    'method' => ['card' => ['credential' => [
        'number' => '4673090000000032',
        'expiryYear' => '30',
        'expiryMonth' => '12',
        'birthOrBusinessRegistrationNumber' => '900101',
        'passwordTwoDigits' => '00',
    ]]],
])->throw();

$billingKey = (string) $issued->json('billingKeyInfo.billingKey');
Http::withHeaders($headers)->delete('https://api.portone.io/billing-keys/'.rawurlencode($billingKey));

$result = ['deleted_key_lookup_status' => Http::withHeaders($headers)->get('https://api.portone.io/billing-keys/'.rawurlencode($billingKey))->json('status')];

try {
    $app->make(BillingKeyGateway::class)->payWithBillingKey('probe-'.bin2hex(random_bytes(8)), $billingKey, 'Declined probe', 1000);
    $result['charge'] = 'UNEXPECTED SUCCESS';
} catch (Throwable $exception) {
    $result['charge'] = 'FAILED';
    $result['exception'] = $exception::class;
    $result['message_prefix'] = mb_substr($exception->getMessage(), 0, 160);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
