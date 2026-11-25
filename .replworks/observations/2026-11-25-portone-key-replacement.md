# 2026-11-25 PortOne billing-key replacement and deletion

Status: active

Boundary: PortOne V2 billing-key REST API, referenced by PRODUCT_SPEC.md External Systems and T-005.

Command: `python3 probes/2026-11-25-portone-key-replacement/probe.py`

Opt-in live observation against the TEST channel. Two billing keys were issued with the observed test card, the first was deleted, and both were looked up before and after. Billing key values are never printed; both keys were deleted by the probe.

| Step | Result |
| --- | --- |
| Issue old key | 200, `billingKeyInfo.billingKey` returned |
| Issue new key | 200, `billingKeyInfo.billingKey` returned |
| Lookup both before deletion | HTTP 200, both status `ISSUED` |
| DELETE old key | HTTP 200, no error code |
| Lookup old key after deletion | HTTP 200, status `DELETED` |
| Delete new key (cleanup) | HTTP 200 |

Consequence: replacement is observed as a new confirmed `ISSUED` key plus a provider-side `DELETED` status for the previous key. A deleted key remains lookup-able with status `DELETED`; the provider does not return 404.
