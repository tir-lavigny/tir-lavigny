<?php

namespace App\Mail;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                $this->invoice->creditor_name ?: (string) config('mail.from.name'),
            ),
            subject: 'Facture '.$this->invoice->invoice_number.' — '.$this->invoice->creditor_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-issued',
            text: 'emails.invoice-issued-text',
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
        return 'facture-'.($this->invoice->invoice_number ?? $this->invoice->id).'.pdf';
    }
}
