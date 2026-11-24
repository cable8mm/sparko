## 1. Languages / runtimes / frameworks / libraries [UNVERIFIED]

- PHP ^8.2
- laravel/framework ^12.0 || ^13.0
- cable8mm/laravel-portone ^0.8.0
- cable8mm/portone-php ^0.8.0, installed through laravel-portone
- inertiajs/inertia-laravel ^2.0, required by the approved Inertia.js with React option
- dompdf/dompdf (version verify)
- React ^18.0 (verify)
- @inertiajs/react (version verify)
- Vite (version verify)
- PHPUnit (version verify)
- PHPStan and Larastan (versions verify)
- Laravel Pint ^1.0
- MySQL 8.0 (verify)
- SQLite in-memory for tests
- Laravel queue, scheduler, and a cache driver that supports atomic locks
- [UNVERIFIED] Confirm the supported versions and compatibility of React, Inertia React, Vite, PHPUnit, PHPStan, Larastan, MySQL, and dompdf/dompdf before creating the project foundation.

## 2. Framework- or tool-mandated paths and file names only

- composer.json
- composer.lock
- package.json
- package-lock.json
- vite.config.js
- phpunit.xml
- phpstan.neon
- .env
- config/plans.php
- config/discounts.php
- app/
- routes/
- config/
- resources/views/
- resources/js/
- database/
- tests/
- public/build/
- .github/workflows/

## 3. Coding conventions specific to the chosen stack [UNVERIFIED]

- PHP code MUST be formatted with Laravel Pint.
- PHP code MUST pass PHPStan with Larastan at the level declared in phpstan.neon.
- Outbound HTTP calls MUST use the Laravel HTTP client unless cable8mm/laravel-portone owns the PortOne call.
- Portal screens MUST be delivered as separately bundled UI assets so the host application can use any frontend stack outside the billing portal. The portal implementation MUST use Blade or Inertia.js with React.
- Code MUST NOT define a payment-provider abstraction layer outside cable8mm/laravel-portone.
- PortOne API calls MUST go through cable8mm/laravel-portone; do not call PortOne endpoints directly.
- PortOne billing-key operations, payment and cancellation requests, payment records, webhook handling, and recurring-charge execution MUST use cable8mm/laravel-portone public APIs; Sparko MUST NOT call PortOne directly.
- Plan-switch and seat-adjustment calculations MUST NOT call PortOne APIs directly.
- Where cable8mm/laravel-portone exposes a public operation to update the amount and cadence used for future renewal charges, Sparko MUST use that operation; Sparko MUST NOT mutate package-owned subscription records directly.
- Invoice PDFs MUST be generated with dompdf/dompdf. Invoice email delivery MUST use Laravel Mail.
- Do not mutate cable8mm/laravel-portone-owned billing-key, payment, webhook, or subscription records outside its public APIs.
- [UNVERIFIED] The released laravel-portone v0.8.0 does not expose a public API to update the amount or cadence used for future renewal charges. Confirm or add that narrow integration API before implementing dependent plan-switch and seat-adjustment work; plan policy, seat pricing, and proration remain Sparko responsibilities.
- [UNVERIFIED] The released laravel-portone v0.8.0 does not expose a public operation to end a recurring schedule immediately without refund. Confirm or add that integration API before implementing dependent entity-deletion work.

## 4. Testing rules

- Tests MUST be written with PHPUnit.
- Test classes MUST be named with the suffix Test.php.
- Feature tests MUST use SQLite in-memory.
- Internal subscription, plan-switch proration, seat, eligibility, invoice, and retry logic MUST have unit tests.
- PortOne-bound behavior MUST have a live E2E test after a live observation; package fakes alone do not satisfy this requirement.

## 5. Configuration rules

- Settings MUST be defined in PHP config files under config/.
- Plan entries MUST be defined in config/plans.php.
- Discount definitions MUST be defined in config/discounts.php; subscriber-specific discount state MUST be persisted by Sparko.
- Plan config MUST support monthly and yearly KRW prices, descriptions, features, trial settings, eligibility, incentives, archived status, and optional seat pricing.
- Plan config MUST distinguish included and unavailable display features and support machine-readable entitlement options.
- Discount codes MUST support fixed KRW and percentage discounts with one-charge, finite-charge, or indefinite durations; a subscription MUST have no more than one active discount.
- Environment-specific values MUST be read from .env through Laravel environment loading.
- Secrets MUST be stored as environment variables and MUST NOT be committed.
- Cached configuration MUST be refreshed after any config file change.
- Subscription charging MUST schedule portone:charge-subscriptions and run queue workers.
- Subscription scheduling MUST use a cache store that supports atomic locks, as required by laravel-portone.

## 6. Build, packaging, and release rules [UNVERIFIED]

- Dependencies MUST be installed with Composer using composer.lock.
- Frontend assets MUST be built with Vite.
- The package MUST be distributed on Packagist as cable8mm/sparko.
- The starter kit MUST be installable with Composer create-project.
- Releases MUST be tagged on GitHub using semantic versioning.
- CI MUST run on GitHub Actions for PHPUnit, Laravel Pint, and PHPStan with Larastan.
- MIT license MUST be applied to the package and the starter kit.
- [UNVERIFIED] Confirm the host application's mail transport configuration before implementing invoice and failed-renewal email delivery.

## 7. Security rules tied to the chosen stack

- PortOne V2 webhook endpoints MUST verify the provider signature before processing.
- Billing key values MUST NOT be written to application logs, events, or unredacted output.
- CI credentials MUST be stored as GitHub Actions secrets.
- Production dependencies MUST be installed without dev packages.
- Invoice and payment-document access MUST be authorized against the owning billable entity.

## 8. Non-goals

- Pest
- Psalm
- PCOV
- Xdebug-based coverage collection
- Redis queue driver
- PostgreSQL
- Private Composer repository
- Laravel Socialite
- cable8mm/laravel-social-auth
- Sign-in and identity-provider integration owned by the host application
- Direct PortOne V2 HTTP API calls outside cable8mm/laravel-portone
- Additional payment-provider abstraction layers
- Stripe SDK
- Paddle SDK
