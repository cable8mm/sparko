# 2026-11-25 PortOne billing-key enrollment

Status: active

Boundary: PortOne V2 billing-key REST API, referenced by PRODUCT_SPEC.md External Systems and T-004.

Command: `python3 probes/2026-11-25-portone-billing-key/probe.py`

Opt-in live observation against the TEST channel using `PORTONE_API_SECRET` and `PORTONE_E2E_CHANNEL_KEY`. Billing key values are redacted in all output; the observed test key was deleted after the probe.

Observed with test card `4673090000000032`, expiry 12/30, birth 900101, password 00:

| Step | Result |
| --- | --- |
| POST /billing-keys | 200, response key `billingKeyInfo` |
| billingKeyInfo fields | `billingKey`, `channels`, `issuedAt`; channel type `TEST`; no `status` field |
| GET /billing-keys/{key} | 200, top-level `status` = `ISSUED`; fields include `billingKey`, `customer`, `methods`, `pgBillingKeyIssueResponses`, `merchantId`, `storeId`, `requestedAt`, `issuedAt` |
| DELETE /billing-keys/{key} | 200 |

Other test card numbers were rejected by the PG (`8108 card number error` or `8126 card not allowed for this merchant`). The accepted card above is the observed test credential for this channel.

Consequence: enrollment must be confirmed by a live lookup returning status `ISSUED` before a billing key is stored. The issue response alone does not carry a status field.
