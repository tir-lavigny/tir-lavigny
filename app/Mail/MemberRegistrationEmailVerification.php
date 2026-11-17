<?php

namespace App\Mail;

use App\Models\MemberRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberRegistrationEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MemberRegistration $registration,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre code de vérification — Société de tir de Lavigny',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-registration-email-verification',
        );
    }

    /** @return array<string> */
    public function attachments(): array
    {
        return [];
    }
}
