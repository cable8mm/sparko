<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipient,
        public readonly string $pdf,
        public readonly string $paymentId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->recipient],
            subject: 'Invoice '.$this->paymentId,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invoice');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->pdf,
                'invoice-'.$this->paymentId.'.pdf',
            ),
        ];
    }
}
