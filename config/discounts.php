<?php

declare(strict_types=1);

/*
| Sparko promotion codes.
|
| Each entry in "codes" uses these keys:
|   code        string  promotion code
|   type        string  "fixed" (KRW) or "percentage"
|   value       int     fixed KRW amount or percentage
|   duration    string  "once", "charges", or "lifetime"
|   charges     ?int    number of charges when duration is "charges"
|   eligibility array   constraints on which plans and billable entities qualify
|   active_from ?string start of the active period
|   active_until ?string end of the active period
*/

return [
    'enabled' => false,

    'codes' => [],
];
