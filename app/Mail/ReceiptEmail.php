<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptEmail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string,mixed> $receipt */
    public function __construct(public readonly array $receipt, private readonly string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Receipt '.$this->receipt['number']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.receipt');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->pdf, 'receipt-'.$this->receipt['number'].'.pdf')->withMime('application/pdf')];
    }
}
