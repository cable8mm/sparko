## Purpose

- Define a Laravel subscription billing portal that implements the customer-facing billing features documented by the latest Laravel Spark documentation, adapted for PortOne V2 and Korean customers.
- Define plan configuration, subscription management, payment-method management, payment documents, and the subscriber portal.
- Portal: the subscriber-facing billing experience.

## Problem

- Korean PG integrations require the application to schedule each recurring charge using an issued billing key.
- Renewal scheduling, retries, cancellation, and duplicate-charge prevention must be coordinated by the application.
- Laravel Spark supports subscription billing portals, but its supported payment providers are Stripe and Paddle.
- Korean social login providers are needed by the target applications.

## Product Goals

- Enable a subscriber to view and choose monthly or yearly plans, including prices, trial periods, descriptions, and features.
- Enable a subscriber to start a subscription using a PortOne billing key.
- Enable a subscriber to view the current plan, billing period, seat quantity when applicable, and next payment.
- Enable a subscriber to switch plans and update the payment method.
- Enable a subscriber to cancel and resume a scheduled cancellation.
- Enable a subscriber to view and download payment documents and receive them by email.
- Enable a subscriber to apply an eligible promotion code to a new or existing subscription.
- Enable an integrator to configure plans, billable entity, eligibility, seats, and billing notifications.
- Enable an integrator to define plan entitlements and use subscription state to gate host-application features.
- Gate the portal behind the host application's completed sign-in.

## Users

- Subscriber: an authenticated user authorized to manage the billable entity's subscription.
- Developer: an integrator who installs the product, selects one billable entity type for the application, and configures plans and billing behavior.
- Billable entity: the configured individual or organization that owns one subscription and its payment methods.

## Inputs

- Plan configuration: plan identifier, name, description, monthly and/or yearly price in KRW, trial settings, features, eligibility rules, seat pricing, incentive text, and archived status.
- Plan entitlements: machine-readable option keys and values used by the host application to determine available capabilities.
- Discount configuration: promotion code, fixed KRW or percentage amount, duration, eligibility, and active status.
- Billing policies: default and per-plan trial duration, payment-method collection during trial, repeat-trial eligibility, invoice email options, and seat-count rules.
- Billing key: a card billing key issued and confirmed by PortOne V2.
- Charge outcomes: payment results delivered through PortOne V2 responses and webhooks.
- Billable information: billing name, billing email, and optional billing address.
- Terms acceptance: the subscriber's acceptance record when terms acceptance is enabled.

## Outputs

- The billing portal displays configured plans, subscription state, payment methods, payment history, and payment documents.
- Subscription state, billing keys, renewal charges, invoices, receipts, seat quantity, and delivery outcomes persist across sessions.
- Successful charges have a downloadable PDF invoice and an associated PortOne receipt when available.
- Configured invoices and failed-renewal notices are emailed to the billable entity's billing email or its custom invoice email.

## Constraints

- Supported host: Laravel application using a billable model selected by the integrator.
- Payment provider: PortOne V2 through cable8mm/laravel-portone.
- Currency: KRW for the initial release.
- Payment method: card billing key for the initial release.
- The billing portal is an installable Laravel feature and must not require the host application to use a particular starter kit.
- The portal's packaged interface and assets must work in a host application regardless of the frontend stack used by the rest of that application.
- The portal is presented in Korean and formats prices as KRW and dates for Korean users.
- Accessibility, performance targets, and retention periods are not specified.

## External Systems

- PortOne V2: billing-key issue, confirmation and deletion; billing-key charges; payment lookup; payment cancellation; webhook delivery; receipt URL.
- Kakao, Naver, Google, and Apple: sign-in assertions, provided by the host application's configured identity integration.
- Email delivery: configured by the host Laravel application.
- PG merchant approval: completed by the integrator's organization before PortOne charges can be made.
- Provider reviews: completed by the integrator's organization before the relevant social sign-in provider can be used.

## User Interface

### SCR-001 Plans and subscription

- ID: SCR-001
- Purpose: show available plans, current subscription, and subscription actions.
- Elements:
  - EL-001 Plan list: name, description, billing interval, price, trial, included and unavailable features, and configured incentive. User action: select or switch to a plan.
  - EL-002 Current subscription: plan, status, billing period, next payment date and amount, and seat quantity when applicable.
  - EL-003 Trial status: trial end date and the first scheduled payment when applicable.
  - EL-004 Cancellation action: schedules cancellation at period end. User action: confirm cancellation.
  - EL-005 Resume action: reverses a scheduled cancellation before period end. User action: resume the subscription.
  - EL-006 Terms acceptance: shown and required before starting a subscription only when configured.
  - EL-007 Seat quantity: current quantity, unit price, and prorated adjustment when the configured plan is per-seat.
  - EL-008 Promotion code: entry and validation feedback when discounts are enabled.
  - EL-009 Return action: navigates to the configured host-application destination.
- States: no subscription, trial, active, cancellation scheduled, past due, expired, loading, and error.
- Navigation:
  - plan selection without an issued billing key -> SCR-002.
  - EL-009 activation -> the configured host-application destination.
- Interactions:
  - plan selection with an issued billing key -> the subscription flow starts.
  - EL-004 confirmation -> cancellation is scheduled at period end.
  - EL-005 activation -> a scheduled cancellation is reversed before period end.
  - an eligible plan switch -> the change applies immediately, prorates the remaining period, and preserves the billing anchor and remaining trial.
  - a failed adjustment charge -> the existing plan remains in place.
  - an archived plan selection -> selection is rejected for a new subscription while the plan remains visible to current subscribers.
  - EL-008 validation success -> the configured discount applies to eligible future subscription charges.

### SCR-002 Payment method and billing details

- ID: SCR-002
- Purpose: issue, display, replace, or remove the billing key and manage billing contact details.
- Elements:
  - EL-010 Billing-key registration action: starts issuance. User action: start registration.
  - EL-011 Registered card display: shows the registered card with masked details.
  - EL-012 Replacement action: registers a new billing key for future renewals. User action: start replacement.
  - EL-013 Removal action: removes the billing key. User action: start removal.
  - EL-014 Billing contact fields: billing name, email, and optional address.
  - EL-015 Invoice email destination: set when custom destinations are enabled.
- States: no payment method, registered, loading, error, and blocked.
- Navigation:
  - completed billing-key registration -> SCR-001.
  - EL-013 activation while removal is blocked -> SCR-002 blocked state.
- Interactions:
  - provider confirmation of issuance -> the billing key is registered.
  - successful replacement -> the new key is used for future charges.
  - a seat quantity change -> the change applies immediately and is prorated; a quantity below one is rejected.
  - EL-013 activation while any non-ended subscription depends on the key -> removal is rejected.

### SCR-003 Payments and invoices

- ID: SCR-003
- Purpose: list subscription charges and their payment documents.
- Elements:
  - EL-016 Payment list: date, amount, plan, billing period, seat quantity when applicable, and outcome.
  - EL-017 Invoice or receipt link: opens the document for a successful charge. User action: open the document.
- States: empty, populated, loading, and error.
- Navigation:
  - EL-017 activation -> SCR-004.
- Interactions:
  - a failed or retried charge -> the outcome remains visible in EL-016.
  - a successful charge -> a PDF invoice is generated.
  - a configured invoice email -> the invoice is sent after a successful charge.
  - a configured failed-renewal notice -> the notice is sent after a failed renewal.

### SCR-004 Invoice or receipt

- ID: SCR-004
- Purpose: display and download the payment document for a successful charge.
- Elements:
  - EL-018 Payment and billing-period details: displays the charge and period of the document.
  - EL-019 Download action: retrieves the document. User action: download the document.
- States: loading, populated, unavailable, and error.
- Navigation:
  - back -> SCR-003.
- Interactions:
  - EL-019 activation with an available document -> the document is downloaded.
  - EL-019 activation with a missing document -> download is disabled and an error is shown.

### SCR-005 Promotion code

- ID: SCR-005
- Purpose: apply a discount to a new or existing subscription.
- Elements:
  - EL-020 Promotion-code entry: accepts a code. User action: submit a code.
  - EL-021 Discount summary: displays the resulting discount.
  - EL-022 Validation feedback: displays validation results.
- States: available, applied, invalid, expired, ineligible, and loading.
- Navigation:
  - an applied code -> SCR-001.
- Interactions:
  - a valid code -> the code is attached to the subscription.
  - an invalid code -> billing state is unchanged.

## Functional Requirements

### Access and billable entity

- FR-001: The billing portal MUST require completed sign-in and authorization for the billable entity.
- FR-002: An application MUST configure one billable entity type for the portal.
- FR-003: A subscriber MUST NOT view or change another billable entity's subscriptions, payment methods, invoices, or billing details.

### Plans

- FR-004: Plan configuration MUST support monthly and yearly prices, name, description, features, trial settings, eligibility, incentive text, and archived status.
- FR-005: The portal MUST display all selectable plans and the current plan; archived plans MUST remain visible to current subscribers.
- FR-006: The system MUST reject invalid plan configuration and ineligible plan selections before charging.
- FR-007: The system MUST switch an existing subscription immediately to another eligible plan, prorate the remaining period, and preserve the billing anchor and remaining trial.
- FR-008: The system MUST support a five-day default trial, per-plan trial overrides, optional upfront payment-method collection, configurable repeat-trial eligibility, and display the trial end and first payment details.
- FR-009: The system MUST support per-seat plans and immediate seat-count changes with prorated charges or credits. Quantity MUST be at least one; a subscriber stops billing by cancelling.
- FR-010: When configured, the subscriber MUST accept the host application's terms before starting a subscription.

### Payment methods and subscriptions

- FR-011: A subscriber MUST be able to issue and confirm a PortOne billing key.
- FR-012: A subscriber MUST be able to replace the billing key used by a subscription.
- FR-013: A billing key MUST NOT be removable while a non-ended subscription depends on it.
- FR-014: A subscription MUST start only after the required trial and payment-method conditions are satisfied.
- FR-015: The portal MUST display subscription status, current period, next payment date and amount, and seat count when applicable.

### Billing and documents

- FR-016: Active subscriptions MUST be charged automatically at each renewal date using the configured plan and seat pricing.
- FR-017: Failed renewals MUST be retried according to the configured schedule and eventually enter a terminal expired state.
- FR-018: A billing period MUST NOT be charged more than once.
- FR-019: PortOne webhooks MUST update the matching payment and subscription state idempotently.
- FR-020: The payment history MUST show each charge with date, amount, plan, period, seat count when applicable, and outcome.
- FR-021: Every successful charge MUST have a downloadable PDF invoice and an associated PortOne receipt when available.
- FR-022: When enabled, invoices MUST be emailed to the configured billing destination.
- FR-023: When enabled, failed-renewal notices MUST be emailed to the billable entity's billing email.

### Cancellation and renewal

- FR-024: Cancellation MUST stop renewals at the end of the current billing period.
- FR-025: A subscriber MUST be able to resume a scheduled cancellation before period end.
- FR-026: Cancellation MUST NOT automatically refund an already paid period.

### Portal integration and discounts

- FR-027: The billing portal MUST be usable in a host application without requiring that application's other screens to use the portal's frontend stack, and MUST provide a configurable return destination.
- FR-028: Plan configuration MUST support machine-readable options, and the integrator MUST be able to inspect the active subscription's options for application feature gating.
- FR-029: The product MUST provide a subscription access gate for host-application routes. Active subscribers continue; other page requests are redirected to the billing portal and asynchronous requests receive HTTP 402.
- FR-030: Plan feature lists MUST distinguish included features from unavailable features.
- FR-031: When discounts are enabled, a subscriber MUST be able to apply an eligible promotion code at subscription start or to an existing subscription. A subscription MUST have at most one active discount; a code's configured fixed or percentage discount and duration MUST be honored. Invalid, expired, or ineligible codes MUST leave the subscription unchanged.
- FR-032: Before a billable entity is deleted, its recurring subscription MUST be ended so that no later renewal charge is scheduled.
- FR-033: The portal MUST present Korean-language UI and format KRW amounts and dates for Korean users.

### PortOne adaptation policies

- FR-034: A decrease from a plan switch or seat change MUST be carried as a credit to the next invoice.
- FR-035: A plan or seat adjustment MUST be committed only after any required adjustment charge succeeds; a failed charge MUST leave the prior plan or seat quantity in effect.
- FR-036: A promotion code applied to an existing subscription MUST affect eligible unpaid and future charges and MUST NOT alter settled charges.
- FR-037: A discount MUST be applied after plan and seat pricing and MUST NOT reduce a charge below zero.
- FR-038: A discount's usage and remaining duration MUST be persisted with the subscription.
- FR-039: Every successful charge MUST have a locally generated PDF invoice, and the PortOne receipt MUST be displayed as a related receipt rather than as the invoice.
- FR-040: A custom invoice email destination MUST be used when configured; otherwise the billable email MUST be used.
- FR-041: Repeat-trial restriction MUST be disabled by default and, when enabled, MUST be expressed as a number of days since the billable entity's previous subscription.
- FR-042: When a trial does not collect a payment method up front, a confirmed billing key MUST be provided before the trial ends and the first charge MUST occur at trial end.
- FR-043: A billable entity deletion MUST end its subscription before removal and MUST NOT automatically refund settled charges.

## User Flows

- New subscription: sign-in -> billable entity authorization -> plan selection -> trial and eligibility check -> billing-key enrollment when required -> subscription start.
- Plan switch: SCR-001 select another eligible plan -> eligibility check -> proration -> adjustment charge -> commit after success or retain prior plan after failure.
- Seat change: SCR-001 or SCR-002 quantity change -> proration -> adjustment charge -> commit after success or retain prior quantity after failure.
- Renewal: renewal date -> one charge for the approved period amount -> outcome recorded -> history and invoice or receipt.
- Failed renewal: declined charge -> retry schedule -> terminal expired state -> configured notice when enabled.
- Promotion code: SCR-005 code entry -> validation -> attachment to a new or existing subscription -> discounted eligible charges.
- Cancellation and resume: SCR-001 cancellation -> period-end stop -> resume action before period end restores renewals.
- Document retrieval: SCR-003 successful charge -> SCR-004 -> download; configured email delivery to the billing or invoice destination.
- Billable entity deletion: end recurring subscription -> entity deletion -> no later renewal charge.
- Host route access: protected request -> active-subscription and plan-option check -> allow, redirect to portal, or asynchronous payment-required response.

## Error Conditions

- ERR-001: Invalid plan configuration or ineligible selection -> no subscription and no charge is created.
- ERR-002: Failed billing-key issuance or confirmation -> no billing-key reference and no subscription start.
- ERR-003: Required adjustment charge failure -> prior plan or seat quantity remains in effect.
- ERR-004: Duplicate or replayed payment event -> no repeated payment or subscription state change.
- ERR-005: Failed renewal -> charge outcome is retained, retries follow the configured schedule, and the terminal expired state is reached.
- ERR-006: Missing or unavailable invoice or receipt -> download is disabled and the document error state is shown.
- ERR-007: Unauthorized billable entity -> no billing information is returned or changed.
- ERR-008: Invalid, expired, or ineligible promotion code -> subscription state is unchanged and validation feedback is shown.
- ERR-009: Removal of a billing key used by a non-ended subscription -> removal is rejected.
- ERR-010: Unavailable payment provider -> an unpaid period is not marked as paid.

## Non-Goals

- Stripe, Paddle, PayPal, or another payment provider.
- Cash receipt issuance or tax invoice issuance.
- A tax calculation or VAT compliance subsystem.
- Team invitation, membership, and general permission-management screens.
- A hosted API or language-agnostic SDK product.
- Replacing the host application's authentication system.

## Acceptance Criteria

- AC-001: The configured billable entity can open the portal after sign-in; another entity's data is never exposed.
- AC-002: Monthly and yearly prices, descriptions, trial terms, features, incentives, and archived status are shown; archived plans remain visible to current subscribers but cannot be newly selected.
- AC-003: Invalid plans and ineligible subscriptions cannot be charged.
- AC-004: A billing key is registered only after PortOne confirms it, and masked details are displayed.
- AC-005: A subscription starts with the selected plan after the approved trial and billing-key conditions are met.
- AC-006: A subscriber can view the current plan, period, next payment, and seat quantity where applicable.
- AC-007: A plan switch is immediate, eligible, prorated, preserves the billing anchor and trial, and applies only after a required charge succeeds.
- AC-008: Seat changes apply immediately with prorated charges or credits; quantity cannot fall below one.
- AC-009: Billing-key replacement changes the key used for future renewals; an in-use key cannot be removed.
- AC-010: Trials use a five-day default, allow configured overrides and upfront key collection, and honor configured repeat-trial restrictions.
- AC-011: At renewal, one charge is made for the configured amount and period; the same period is never charged twice.
- AC-012: Failed renewals follow the configured retry schedule, appear in history, and eventually reach the terminal expired state.
- AC-013: A repeated PortOne webhook does not repeat payment or subscription state changes.
- AC-014: Every successful charge appears in payment history with a downloadable PDF invoice and associated PortOne receipt when available.
- AC-015: Enabled invoice emails are sent to the configured invoice destination, or to the billable email when no custom destination is configured.
- AC-016: Cancellation takes effect at period end without an automatic refund; cancellation can be resumed before then.
- AC-017: Configured terms must be accepted before starting a subscription.
- AC-018: The active plan exposes its configured machine-readable options, and the route access gate allows active subscribers while redirecting page requests and returning 402 for asynchronous requests without an active subscription.
- AC-019: A plan with included and unavailable features displays the two groups distinctly.
- AC-020: A valid promotion code applies its fixed or percentage discount for the configured duration to a new or existing subscription; only one discount is active, and invalid, expired, or ineligible codes do not change billing state.
- AC-021: The portal works alongside a host application using a different frontend stack, returns to the configured host destination, and presents Korean UI with KRW and Korean date formatting.
- AC-022: Deleting a billable entity ends its recurring subscription before deletion and creates no later renewal charge.
- AC-023: Enabled failed-renewal notices are sent to the billable entity's billing email after a failed renewal.
