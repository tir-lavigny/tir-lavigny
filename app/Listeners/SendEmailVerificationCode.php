<?php

namespace App\Listeners;

use App\Events\MemberRegistrationFormSubmitted;
use App\Services\MemberRegistrationEmailVerificationService;

class SendEmailVerificationCode
{
    public function __construct(private readonly MemberRegistrationEmailVerificationService $verification) {}

    public function handle(MemberRegistrationFormSubmitted $event): void
    {
        $this->verification->sendCode($event->registration);
    }
}
