<?php

declare(strict_types=1);

/*
| Sparko plan definitions.
|
| Each entry in "plans" uses these keys:
|   id                    string  stable plan identifier
|   name                  string  display name
|   description           string  display description
|   prices                array   "monthly" and/or "yearly" KRW amounts
|   trial_days            ?int    override of default_trial_days
|   trial_requires_payment_method ?bool override of the global up-front key requirement
|   features              array   "included" and "unavailable" display lists
|   options               array   machine-readable entitlement keys and values
|   eligibility           array   constraints on who may select the plan
|   incentive             ?string display incentive text
|   archived              bool    visible to current subscribers only
|   seat_pricing          array   "enabled" and "unit_price" in KRW
*/

return [
    'default_trial_days' => 5,
    'trial_requires_payment_method' => false,

    'repeat_trial' => [
        'enabled' => false,
        'since_last_subscription_days' => null,
    ],

    'plans' => [],
];
