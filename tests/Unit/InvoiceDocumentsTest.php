<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Mail\InvoiceMail;
use App\Models\User;
use App\Sparko\Documents\InvoiceGenerator;
use Cable8mm\LaravelPortone\Events\PaymentCompleted;
use Cable8mm\LaravelPortone\Events\PaymentEventSource;
use Cable8mm\LaravelPortone\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function payment(User $user): Payment
    {
        return Payment::query()->create([
            'payment_id' => 'payment-'.bin2hex(random_bytes(8)),
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
            'customer_id' => 'customer-1',
            'order_name' => 'Subscription renewal',
            'amount' => 10000,
            'status' => 'PAID',
            'cancelled_amount' => 0,
            'paid_at' => now(),
        ]);
    }

    public function test_a_successful_charge_generates_a_pdf_invoice(): void
    {
        $pdf = app(InvoiceGenerator::class)->pdf($this->payment(User::factory()->create()));

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_an_enabled_invoice_email_is_sent_to_the_configured_destination(): void
    {
        config()->set('sparko.email_invoices', true);
        config()->set('sparko.invoice_email', 'invoices@example.com');
        Mail::fake();

        $user = User::factory()->create(['email' => 'owner@example.com']);
        event(new PaymentCompleted($this->payment($user), PaymentEventSource::Charge));

        Mail::assertSent(InvoiceMail::class, fn (Mailable $mail): bool => $mail->hasTo('invoices@example.com'));
    }

    public function test_invoice_email_is_disabled_by_default(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);

        event(new PaymentCompleted($this->payment($user), PaymentEventSource::Charge));

        Mail::assertNothingSent();
    }
}
