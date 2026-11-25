<?php

namespace App\Mail;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public InvoiceReminder $reminder,
        public string $pdfContent,
        public ?string $replyToEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        $senderName = $this->invoice->creditor_name ?: (string) config('mail.from.name');
        $replyTo = $this->replyToEmail
            ? [new Address($this->replyToEmail, $senderName)]
            : [];

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $senderName),
            replyTo: $replyTo,
            subject: 'Rappel de paiement — Facture '.$this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-reminder',
            text: 'emails.invoice-reminder-text',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfContent, $this->attachmentName())
                ->withMime('application/pdf'),
        ];
    }

    private function attachmentName(): string
    {
        return 'rappel-facture-'.($this->invoice->invoice_number ?? $this->invoice->id).'.pdf';
    }
}
