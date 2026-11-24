# 2026-11-25 PortOne subscription operations API

Status: active

Boundary: cable8mm/laravel-portone v0.8.0 public operations surface, referenced by TECH_STACK.md sections 1 and 3.

Command: `php probes/2026-11-25-portone-subscription-api/probe.php`

Observed public methods on `SubscriptionOperations`: `subscriptions`, `subscribe`, `dueIds`, `charge`, `cancel`, `resume`, `changeBillingKey`.

Observed public methods on the `Billable` concern: `subscriptions`, `chargeBillingKey`, `subscribe`.

`SubscriptionLifecycle::end(CarbonInterface $now)` exists but is a pure decision helper that returns values for the caller to persist. It is not an operation that terminates a recurring schedule and is excluded from the public operations surface.

| Capability | Result |
| --- | --- |
| Update future renewal amount or cadence | FAIL - not exposed |
| End a recurring schedule immediately without refund | FAIL - not exposed |

Consequence: plan switches, seat adjustments, and billable-entity deletion cannot rely on a package operation to change or stop a running recurring charge. A decision is required before dependent task work.

`cancel(Subscription $subscription)` exists and schedules the end at period end. It does not end the schedule immediately.
