<?php

namespace App\Mail;

use App\Models\MemberRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberRegistrationCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MemberRegistration $registration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inscription complétée - '.$this->registration->form_data['first_name'].' '.$this->registration->form_data['last_name'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-registration-completed',
        );
    }

    /** @return array<string> */
    public function attachments(): array
    {
        return [];
    }
}
