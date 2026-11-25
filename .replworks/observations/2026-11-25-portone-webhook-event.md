# 2026-11-25 PortOne subscription charge webhook event

Status: active

Boundary: PortOne V2 charge event delivered through webhook, referenced by PRODUCT_SPEC.md Billing and documents and T-009.

Command: `python3 probes/2026-11-25-portone-webhook-event/probe.py`

A confirmed billing key was charged on the TEST channel and the resulting payment was looked up at PortOne. Observed fields:

| Field | Value |
| --- | --- |
| charge HTTP status | 200 |
| payment lookup status | `PAID` |
| amount.total | 1000 |
| transactionId present | true |
| storeId present | true |
| receiptUrl present | true |

The full lookup response keys are recorded by the probe; payment and billing-key identifiers are not persisted in this report. The event payload used by the webhook E2E test is built from these observed fields (`Transaction.Paid`, `paymentId`, `storeId`, `transactionId`) and signed with the configured webhook secret.
