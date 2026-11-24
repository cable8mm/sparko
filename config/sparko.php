<?php

declare(strict_types=1);
use App\Models\User;

/*
| The application configures exactly one billable entity type. The configured
| model MUST implement App\Sparko\Contracts\BillableEntity so the portal can
| authorize access to the entity's subscriptions, payment methods, invoices,
| and billing details.
*/

return [
    'billable_model' => env('SPARKO_BILLABLE_MODEL', User::class),
];
