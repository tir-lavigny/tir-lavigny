<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\MemberRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberDavSyncFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MemberRegistration $registration,
        public Member $member,
        public string $error,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Synchronisation DAV échouée — '.$this->member->first_name.' '.$this->member->last_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-dav-sync-failed',
        );
    }

    /** @return array<string> */
    public function attachments(): array
    {
        return [];
    }
}
