<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FailedRenewalNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $billingEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->billingEmail],
            subject: 'Subscription renewal failed',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.failed-renewal');
    }
}
