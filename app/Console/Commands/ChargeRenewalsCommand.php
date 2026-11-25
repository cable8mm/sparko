<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sparko\Subscriptions\RenewalService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ChargeRenewalsCommand extends Command
{
    protected $signature = 'sparko:charge-renewals';

    protected $description = 'Charge subscriptions that are due using their approved period amount.';

    public function handle(RenewalService $service): int
    {
        $charged = $service->chargeDue(CarbonImmutable::now());

        $this->info(sprintf('Charged %d due subscription(s).', $charged));

        return self::SUCCESS;
    }
}
