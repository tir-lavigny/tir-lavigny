<?php

namespace App\Listeners;

use App\Events\MemberRegistrationCompleted;
use App\Mail\MemberRegistrationCompleted as MemberRegistrationCompletedMail;
use App\Mail\MemberRegistrationError;
use App\MemberRegistrationStatus;
use Illuminate\Support\Facades\Mail;

class SendCompletionEmail
{
    public function handle(MemberRegistrationCompleted $event): void
    {
        $registration = $event->registration;

        try {
            Mail::to(env('APP_USER_EMAIL'))->send(new MemberRegistrationCompletedMail($registration));

            $registration->status = MemberRegistrationStatus::COMPLETED;
            $registration->save();
        } catch (\Throwable $e) {
            $registration->error_message = 'Erreur email : '.$e->getMessage();
            $registration->failed_at = now();
            $registration->save();

            Mail::to(env('APP_USER_EMAIL'))->send(new MemberRegistrationError($registration));
        }
    }
}
