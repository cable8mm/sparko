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
    'require_terms' => env('SPARKO_REQUIRE_TERMS', false),
    'notify_failed_renewal' => env('SPARKO_NOTIFY_FAILED_RENEWAL', false),
    'email_invoices' => env('SPARKO_EMAIL_INVOICES', false),
    'invoice_email' => env('SPARKO_INVOICE_EMAIL'),
    'return_url' => env('SPARKO_RETURN_URL', '/'),
    'live_billing_key_e2e' => env('SPARKO_LIVE_BILLING_KEY_E2E', false),
    'e2e_channel_key' => env('PORTONE_E2E_CHANNEL_KEY'),
];
