<?php

namespace App\Providers;

use App\Listeners\EndSubscriptionBeforeBillableDeletion;
use App\Listeners\SendInvoiceAfterPayment;
use Cable8mm\LaravelPortone\Events\PaymentCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(PaymentCompleted::class, SendInvoiceAfterPayment::class);

        $billableModel = config('sparko.billable_model');

        if (is_string($billableModel) && class_exists($billableModel)) {
            $billableModel::deleting(EndSubscriptionBeforeBillableDeletion::class);
        }
    }
}
