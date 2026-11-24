<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Cable8mm\LaravelPortone\Concerns\Billable;
use Cable8mm\LaravelPortone\SubscriptionOperations;

// Only the public operations surface counts. SubscriptionLifecycle is a pure
// decision helper that returns values for the caller to persist; its end()
// method is not an operation that terminates a recurring schedule.
$expected = [
    'renewal amount/cadence update' => [
        SubscriptionOperations::class => ['updateAmount', 'changeAmount', 'updateInterval', 'changeInterval', 'updatePlan', 'changePlan'],
        Billable::class => ['updateAmount', 'changeAmount', 'updateInterval', 'changeInterval', 'updatePlan', 'changePlan'],
    ],
    'immediate schedule termination without refund' => [
        SubscriptionOperations::class => ['end', 'endNow', 'terminate', 'stop', 'endImmediately'],
        Billable::class => ['end', 'endNow', 'terminate', 'stop', 'endImmediately'],
    ],
];

$result = [];
foreach ($expected as $capability => $classes) {
    $found = [];
    foreach ($classes as $class => $methods) {
        foreach ($methods as $method) {
            if (method_exists($class, $method)) {
                $found[] = $class.'::'.$method;
            }
        }
    }
    $result[$capability] = [
        'observed' => $found,
        'available' => $found !== [],
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

foreach ($result as $capability => $data) {
    fwrite(STDERR, sprintf("%s: %s\n", $capability, $data['available'] ? 'PASS' : 'FAIL (not exposed)'));
}
