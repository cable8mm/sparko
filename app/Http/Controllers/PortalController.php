<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Sparko\Contracts\BillableEntity;
use App\Sparko\Plans\PlanCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PortalController extends Controller
{
    public function show(mixed $billable, PlanCatalog $catalog): View
    {
        if (! $billable instanceof Model || ! $billable instanceof BillableEntity) {
            throw new InvalidArgumentException('The resolved billable must be an Eloquent model implementing BillableEntity.');
        }

        return view('sparko.portal', [
            'billable' => $billable,
            'plans' => $catalog->forPortal($billable, null),
            'currentPlanId' => null,
        ]);
    }
}
