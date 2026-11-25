# 2026-11-25 PortOne receipt URL

Status: active

Boundary: PortOne V2 payment lookup, referenced by T-010.

Command: `python3 probes/2026-11-25-portone-receipt/probe.py`

A confirmed billing key was charged on the TEST channel and the resulting payment was looked up.

| Field | Value |
| --- | --- |
| payment status | `PAID` |
| receiptUrl present | true |
| receipt URL scheme | `https` |
| receipt URL host | `testpartner.kcp.co.kr` |

The full receipt URL is not persisted in this report. The package's `Payment` model stores the URL returned by the provider lookup, so the portal can link to it for a successful charge.
