<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Mail\InvoiceMail;
use App\Sparko\Documents\InvoiceGenerator;
use Cable8mm\LaravelPortone\Events\PaymentCompleted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class SendInvoiceAfterPayment
{
    public function __construct(private readonly InvoiceGenerator $invoices) {}

    public function handle(PaymentCompleted $event): void
    {
        if (config('sparko.email_invoices') !== true) {
            return;
        }

        $class = $event->billableType;

        if (! class_exists($class)) {
            return;
        }

        $billable = $class::query()->find((int) $event->billableId);

        if (! $billable instanceof Model) {
            return;
        }

        $configured = config('sparko.invoice_email');
        $recipient = is_string($configured) && $configured !== '' ? $configured : data_get($billable, 'email');

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        Mail::to($recipient)->send(new InvoiceMail(
            $recipient,
            $this->invoices->pdf($event->payment),
            $event->paymentId,
        ));
    }
}
