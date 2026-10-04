<?php

namespace App\Mail;

use App\Models\MemberRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberRegistrationValidationRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MemberRegistration $registration,
    ) {}

    public function envelope(): Envelope
    {
        $firstName = $this->registration->form_data['first_name'] ?? 'Inconnu';
        $lastName = $this->registration->form_data['last_name'] ?? 'Inconnu';

        return new Envelope(
            subject: "Inscription à valider — {$lastName} {$firstName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-registration-validation-requested',
        );
    }

    /** @return array<string> */
    public function attachments(): array
    {
        return [];
    }
}
