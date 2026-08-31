<?php

namespace App\Mail;

use App\Models\OperatingDay;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperatingDayClosed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OperatingDay $operatingDay,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Clôture journée du '.$this->operatingDay->date->format('d.m.Y').' — '.$this->operatingDay->season->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operating-day-closed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
