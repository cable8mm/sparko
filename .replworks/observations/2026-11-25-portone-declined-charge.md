# 2026-11-25 PortOne declined billing-key charge

Status: active

Boundary: PortOne V2 billing-key charge through cable8mm/laravel-portone, referenced by T-008.

Command: `php probes/2026-11-25-portone-declined-charge/probe.php`

A billing key was issued on the TEST channel, deleted at the provider, and then used for a charge through the package's public gateway.

| Step | Result |
| --- | --- |
| Lookup after deletion | status `DELETED` |
| Charge with deleted key | FAILED |
| Exception | `Cable8mm\PortonePhp\Exception\UnknownException` |
| Message | `PortOne returned an error response.` |

Consequence: a declined renewal must be treated as a failed charge, not a paid period. The retry schedule is owned by Sparko because the package does not expose renewal retries for Sparko's own subscription record.
