<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Sparko\Contracts\BillableEntity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class PortalController extends Controller
{
    /**
     * @param  Model&BillableEntity  $billable
     */
    public function show($billable): View
    {
        return view('sparko.portal', ['billable' => $billable]);
    }
}
