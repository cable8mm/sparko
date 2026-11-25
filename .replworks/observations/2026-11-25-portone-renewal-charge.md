# 2026-11-25 PortOne billing-key renewal charge

Status: active

Boundary: PortOne V2 billing-key charge REST path through cable8mm/laravel-portone, referenced by PRODUCT_SPEC.md Billing and documents and T-007.

Evidence: the opt-in live E2E `LivePlanSwitchE2ETest` executed at 2026-11-25T06:10:44Z against the TEST channel. It issued a billing key, enrolled it, started a subscription, and called `PaymentOperations::chargeBillingKey()` through the package. Output:

```
[live plan switch] 2026-11-25T06:10:44+00:00 status=200 amount=20000
Tests: 3 passed (19 assertions)
```

The test asserts the resulting `portone_payments` record status is `PAID`. The billing key was deleted at the provider after the run.

Observed behavior relied on by renewal work:

- A confirmed `ISSUED` key produces a `PAID` payment through the package public charge operation.
- The charge amount is supplied by the caller; PortOne does not choose the renewal amount.
- A failed or unavailable provider leaves the local charge record unpaid, so a period must not be marked paid until the package confirms `PAID`.

No public operation exists to change a running package subscription's amount or cadence (see `2026-11-25-portone-subscription-api.md`), so Sparko tracks the next charge time and amount in its own subscription record.
