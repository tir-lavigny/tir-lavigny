<?php

namespace App\Listeners;

use App\Events\MemberRegistrationDavCreated;
use App\Events\MemberRegistrationFormSubmitted;
use App\Mail\MemberRegistrationError;
use App\MemberRegistrationStatus;
use App\Models\User;
use App\Services\InfomaniakDavService;
use Illuminate\Support\Facades\Mail;

class CreateDavContact
{
    public function __construct(private readonly InfomaniakDavService $dav) {}

    public function handle(MemberRegistrationFormSubmitted $event): void
    {
        $registration = $event->registration;

        try {
            $result = $this->dav->createContactFromMemberRegistration($registration);

            $registration->dav_uid = $result['uid'];
            $registration->status = MemberRegistrationStatus::DAV_CREATED;
            $registration->save();

            MemberRegistrationDavCreated::dispatch($registration);
        } catch (\Throwable $e) {
            $registration->error_message = 'Erreur DAV : '.$e->getMessage();
            $registration->failed_at = now();
            $registration->save();

            Mail::to(User::getMemberRegistrationEmails())->send(new MemberRegistrationError($registration));
        }
    }
}
