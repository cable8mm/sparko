## Purpose

- Define how the subscription billing portal works internally.
- Define component ownership, information flow, and invariants.
- Describe the payment lifecycle without duplicating implementation constraints.

## Core Concepts

- Billable entity: the individual or organization that owns a subscription, payment methods, and billing documents.
- Plan: a configured offering with monthly and/or yearly prices, trial settings, features, eligibility, and optional seat pricing.
- Plan options: machine-readable entitlement values associated with a plan and used by the host application to gate capabilities.
- Promotion code: a code that requests a configured discount on an eligible subscription charge.
- Trial: a configured period before the first subscription charge.
- Subscription: a billable entity's relationship to a plan and billing key, including its current period, next charge, and status.
- Seat quantity: the billable usage count used to calculate a per-seat plan charge.
- Charge: a payment record for a subscription period or renewal attempt.
- Payment event: an externally delivered or synchronously confirmed outcome for a charge.
- Payment credential: the confirmed reusable payment credential held by the payment method manager.
- Payment document: the invoice or hosted payment receipt associated with a successful charge.
- Cancellation: a scheduled stop at the end of the current period.
- Plan switch: a change from the current plan to another eligible configured plan.

## System Flow

- Plan definitions are validated before display or subscription.
- Plan definitions provide display features and machine-readable options; unavailable features are visibly distinguished in the portal.
- The authenticated subscriber is resolved to the configured billable entity and authorized before billing data is exposed.
- A plan selection is checked for eligibility and terms acceptance.
- A trial and billing-key requirement are applied before the first subscription charge.
- A successful subscription start captures its plan, period, price basis, and billable quantity.
- The Subscription Manager owns product plan changes, seat quantities, and proration calculations; the payment integration executes any resulting charge and maintains the recurring-charge schedule.
- A plan switch or seat update is eligibility-checked, prorates the remaining period, preserves the billing anchor and trial end, and commits only after an immediate charge succeeds when the adjustment is positive.
- The renewal scheduler finds due subscriptions and requests a charge using the price basis assigned to that period.
- Charge outcomes update charge history and subscription state once.
- Plan switches, seat changes, cancellation, and resume are applied under the product policies.
- Successful charges feed the invoice or receipt view and any configured email delivery.
- A promotion code is validated before it is attached to a subscription, and its discount is included in eligible charge calculations.
- Before a billable entity is deleted, its recurring subscription is ended.

## Components

- Plan Catalog: validates, exposes, and archives plan definitions.
- Billable Resolver: resolves and authorizes the configured billing entity.
- Subscription Manager: owns subscription lifecycle and plan state.
- Payment Integration: owns payment credentials, payment records, provider communication, and recurring-charge execution.
- Entitlement Gate: resolves active subscription options and enforces the subscription requirement for protected host routes.
- Discount Manager: validates promotion codes, associates one active discount with a subscription, and provides discount inputs to charge calculation.
- Trial Manager: owns trial state and first-charge eligibility.
- Seat Calculator: determines billable seat quantity and price basis.
- Payment Method Manager: owns billing-key enrollment, replacement, and removal decisions.
- Renewal Scheduler: determines due renewals and retries.
- Charge Event Processor: applies charge outcomes idempotently.
- Invoice Provider: maps a successful charge to its invoice or receipt and delivery.
- Billing Notifier: sends configured billing documents and failure notices.

## Component Responsibilities

### Plan Catalog

- Responsibilities: validate plan identifiers, prices, intervals, trials, descriptions, features, eligibility, incentive text, archived status, and optional seat pricing; expose selectable plans and retain archived plans for current subscribers; expose machine-readable options and included/unavailable features.
- Inputs: configured plan definitions.
- Outputs: validated plan entries; eligibility results; display features; machine-readable options.
- Ownership boundaries: owns plan definition validity; does not own subscription state.

### Entitlement Gate

- Responsibilities: check whether the billable entity has an active subscription; read its plan options; allow protected requests for active subscribers; redirect page requests without an active subscription; return an unauthorized-payment response for asynchronous requests.
- Inputs: protected host route request; subscription state; plan options.
- Outputs: allow decision; redirect decision; unauthorized-payment response.
- Ownership boundaries: does not mutate subscription state.

### Billable Resolver

- Responsibilities: resolve the authenticated subscriber to the one configured billable entity type; enforce access to that entity's subscriptions, payment methods, and billing records.
- Inputs: authenticated subscriber identity; configured billable entity type.
- Outputs: authorized billable entity; authorization decision.
- Ownership boundaries: does not change billing state.

### Subscription Manager

- Responsibilities: start subscriptions after eligibility, terms, trial, and payment-method conditions; own plan, period, status, cancellation, resume, and approved plan-switch state; apply accepted renewal outcomes; calculate plan and seat adjustments; commit immediate plan switches only after required adjustment payment succeeds.
- Inputs: plan selection; eligibility result; terms acceptance; trial result; payment-method confirmation; adjustment charge result; cancellation and resume commands.
- Outputs: subscription state; current period; approved price basis; plan-switch and seat state.
- Ownership boundaries: the only owner of subscription state; product proration policy is not delegated to the payment integration; does not write payment records or change provider communication state.

### Payment Integration

- Responsibilities: register and delete reusable payment credentials through the external payment boundary; create and record charges including renewals; maintain the recurring-charge schedule; expose an operation to change the amount and cadence used by future renewal charges where the external boundary provides one.
- Inputs: credential registration and deletion requests; charge requests; approved price basis; schedule changes.
- Outputs: payment credentials; payment records; charge outcomes; recurring-charge schedule state.
- Ownership boundaries: owns payment credentials, payment and webhook records, and recurring-charge execution; does not choose plans, determine eligibility, calculate pricing or proration, or decide when a product plan change takes effect.

### Trial Manager

- Responsibilities: determine trial start and end and whether the first charge is due; prevent a charge before the configured trial end; apply the confirmed re-trial eligibility policy.
- Inputs: plan trial settings; billable entity history; payment-method collection setting.
- Outputs: trial state; trial end; first-charge eligibility.
- Ownership boundaries: owns trial state; does not initiate charges.

### Seat Calculator

- Responsibilities: determine current billable quantity from the configured usage rule; supply the quantity and resulting charge amount; apply quantity changes immediately with a minimum of one using the same proration rules as plan switches.
- Inputs: usage rule; quantity change command; plan price basis.
- Outputs: billable quantity; period price basis; adjustment amount.
- Ownership boundaries: does not mutate subscription state.

### Discount Manager

- Responsibilities: validate promotion-code status, eligibility, and configured duration; ensure no more than one active discount per subscription; supply the fixed or percentage discount to eligible charge calculations.
- Inputs: promotion code; subscription state; discount configuration.
- Outputs: validation result; active discount; discount input for charge calculation.
- Ownership boundaries: does not initiate charges or alter settled payment records.

### Payment Method Manager

- Responsibilities: register a billing key only after provider confirmation; replace the key used for future charges; block removal while a subscription depends on it; supply masked card details.
- Inputs: enrollment result; replacement and removal requests; subscription state.
- Outputs: billing-key reference; masked card details; removal decision.
- Ownership boundaries: owns billing-key references; does not expose the plaintext key.

### Renewal Scheduler

- Responsibilities: find due renewals and retries; request one charge for the period's approved price basis.
- Inputs: subscription state; period price basis; charge outcomes.
- Outputs: charge requests; retry schedule.
- Ownership boundaries: does not write payment records or mutate subscription state.

### Charge Event Processor

- Responsibilities: apply a confirmed charge result to the matching payment and subscription; ignore repeated event deliveries and repeated payment outcomes.
- Inputs: external payment events; synchronous charge confirmations.
- Outputs: accepted outcomes; idempotency decision.
- Ownership boundaries: does not initiate charges.

### Invoice Provider

- Responsibilities: generate an invoice for each successful charge; associate the external payment receipt when available; provide document availability, authorized retrieval, and delivery destination to the portal.
- Inputs: successful payment records; receipt references; billable entity.
- Outputs: invoice documents; availability decision; delivery destination.
- Ownership boundaries: does not change payment records.

### Billing Notifier

- Responsibilities: send invoice documents and failed-renewal notices when configured; use the billable entity's email or the configured invoice email destination.
- Inputs: invoice availability; failed-renewal outcomes; configured destinations.
- Outputs: delivery outcomes.
- Ownership boundaries: does not own invoice contents or subscription state.

## Responsibility Boundaries

- Subscription state has one owner: the Subscription Manager.
- Payment credentials, payment and webhook records, and recurring-charge execution remain owned by the payment integration.
- Product subscription state, plan eligibility, seat quantity, and proration policy remain owned by Sparko's Subscription Manager and Seat Calculator.
- Plan definitions have one owner: the Plan Catalog.
- Billing-key references have one owner: the Payment Method Manager.
- Charge initiation and charge result processing are separate.
- Invoice retrieval and email delivery do not mutate payment outcomes.
- Every billing read and action is scoped to the resolved billable entity.

## Data Flow

- Plan configuration -> Plan Catalog -> plan list and eligibility result.
- Authenticated subscriber -> Billable Resolver -> authorized billable entity.
- Plan selection -> Subscription Manager; trial and payment-method results are checked before charge.
- Billable entity usage -> Seat Calculator -> period price basis -> Renewal Scheduler.
- Plan or seat adjustment -> Subscription Manager and Seat Calculator -> adjustment charge and future renewal amount through Payment Integration -> Subscription Manager commits after the required charge succeeds.
- Subscription state -> Renewal Scheduler -> external charge request.
- Payment confirmation or verified charge event -> Charge Event Processor -> payment records and Subscription Manager.
- Successful payment record -> Invoice Provider -> portal document and Billing Notifier.
- Cancellation, resume, plan switch, and seat updates -> Subscription Manager and Seat Calculator under approved policies.
- Protected host route -> Entitlement Gate -> active subscription and plan options -> allow, redirect, or asynchronous payment-required response.
- Promotion code -> Discount Manager -> validation and subscription discount -> charge calculation.
- Billable entity deletion -> Subscription Manager ends recurring subscription -> entity deletion proceeds.

## Architectural Rules

- Only the Subscription Manager MUST mutate subscription state.
- Payment records and payment credential records MUST be changed through the payment integration boundary.
- The Subscription Manager MUST determine plan and seat adjustments before requesting a charge or changing the amount used for future renewals through the payment integration.
- The Plan Catalog MUST reject invalid plans before they are exposed or charged.
- The Billable Resolver MUST authorize every portal action against the owning billable entity.
- A subscription period MUST NOT be charged more than once.
- An external payment event MUST be applied at most once.
- A payment method used by a non-ended subscription MUST NOT be removable.
- A successful charge MUST have an available invoice before it is shown as complete.
- A plan switch or seat change MUST preserve the billing anchor and trial end and MUST NOT commit if its immediate adjustment charge fails.
- A seat quantity MUST be at least one.
- A subscription MUST have no more than one active discount.
- A promotion code MUST NOT change a settled charge.
- A billable entity MUST NOT be deleted while its recurring subscription remains scheduled to renew.
- Cancellation MUST take effect at the approved billing boundary and MUST NOT automatically refund a paid period.

## Failure Boundaries

- Invalid plan or failed eligibility -> no subscription or charge is created.
- Failed billing-key issuance -> no billing-key reference and no subscription start.
- Failed first subscription payment -> no active subscription.
- Duplicate or replayed external payment event -> no repeated state change.
- Failed renewal -> charge outcome is retained and the subscription follows the configured retry and terminal-state rules.
- Invoice generation failure -> the charge remains recorded and invoice generation can be retried; the portal shows the document as unavailable until generated.
- Email delivery failure -> billing state remains unchanged and delivery can be retried independently.
- Unauthorized billable entity -> no billing information is returned or changed.

## Non-Goals

- A generic payment-provider adapter layer.
- Provider-specific tax calculation or tax-document issuance.
- Team invitations and general membership permissions.
- A hosted API or SDK product.

## Architectural Invariants

- A subscription period MUST NOT be charged more than once.
- A successful charge has exactly one generated invoice.
- Subscription state has one owner.
- Payment credentials and payment records have one owner.
- A plan switch or seat change preserves the billing anchor and trial end.
- A decrease from an adjustment is carried as a credit, not a refund.
- A subscription has at most one active discount.
- A discount never changes a settled charge.
- A billable entity deletion never leaves a recurring charge scheduled.
- Every billing read and action is scoped to the resolved billable entity.

## Resolved Decisions

- Ambiguous: an earlier policy reference excluded plan changes and seat billing. Decided: follow the Laravel Spark billing policy for plan switches, seats, trials, and discounts. Why: the product is the Korean adaptation of Laravel Spark, and the current product specification requires these behaviors; the earlier exclusion is superseded.
- Ambiguous: the external payment boundary may not expose an operation to change the amount and cadence of future renewal charges. Decided: the Subscription Manager owns plan and seat adjustment policy, the Payment Integration exposes the operation only where the boundary provides one, and availability is verified before dependent work. Why: product proration policy must not be delegated to the payment integration, and no implementation may assume a capability that has not been observed.
- Ambiguous: a decrease from a plan or seat adjustment could be refunded or credited. Decided: carry it as a credit to the next invoice. Why: no automatic refund is given for changes or cancellation within a paid period.
- Ambiguous: the state after the final failed renewal retry. Decided: the subscription reaches a terminal expired state. Why: the product specification defines a terminal state for failed renewals.
- Ambiguous: ownership of the recurring-charge schedule when plan and seat changes alter future amounts. Decided: the Payment Integration owns the schedule and exposes the change operation; the Subscription Manager supplies the approved price basis. Why: charge execution and product pricing policy are separate responsibilities.

