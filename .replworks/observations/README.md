# Observations index

- 2026-11-25 portone-subscription-api — active. Renewal amount/cadence update and immediate schedule termination are not exposed by laravel-portone v0.8.0. Report: `2026-11-25-portone-subscription-api.md`.
- 2026-11-25 portone-billing-key — active. Live TEST-channel enrollment returns a key; confirmation requires a separate lookup returning status ISSUED. Report: `2026-11-25-portone-billing-key.md`.
- 2026-11-25 portone-key-replacement — active. Replacement is a new ISSUED key plus provider-side DELETED status for the old key; deleted keys remain lookup-able. Report: `2026-11-25-portone-key-replacement.md`.
- 2026-11-25 portone-renewal-charge — active. A confirmed key produces a PAID payment through the package charge operation; amount is caller-supplied. Report: `2026-11-25-portone-renewal-charge.md`.
- 2026-11-25 portone-declined-charge — active. A deleted key produces a failed charge and no paid period. Report: `2026-11-25-portone-declined-charge.md`.
