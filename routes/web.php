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
        Route::post('/{billable}/payment-method', [PortalController::class, 'enrollPaymentMethod'])->name('sparko.payment-method.store');
        Route::post('/{billable}/payment-method/replace', [PortalController::class, 'replacePaymentMethod'])->name('sparko.payment-method.replace');
        Route::delete('/{billable}/payment-method', [PortalController::class, 'removePaymentMethod'])->name('sparko.payment-method.destroy');
        Route::post('/{billable}/subscription', [PortalController::class, 'startSubscription'])->name('sparko.subscription.store');
        Route::post('/{billable}/subscription/plan', [PortalController::class, 'switchPlan'])->name('sparko.subscription.plan');
        Route::get('/{billable}/payments/{payment}/invoice', [PortalController::class, 'downloadInvoice'])->name('sparko.invoice.download');
    });
