<?php

use App\Http\Controllers\PortalController;
use App\Http\Middleware\EnsureBillableAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', EnsureBillableAccess::class])
    ->prefix('billing')
    ->group(function (): void {
        Route::get('/{billable}', [PortalController::class, 'show'])->name('sparko.portal');
    });
