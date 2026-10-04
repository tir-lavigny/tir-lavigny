<?php

namespace App\Listeners;

use App\Events\MemberRegistrationFormSubmitted;
use App\Services\MemberRegistrationPipelineService;

class SendValidationRequestEmail
{
    public function __construct(private readonly MemberRegistrationPipelineService $pipeline) {}

    public function handle(MemberRegistrationFormSubmitted $event): void
    {
        $this->pipeline->sendValidationRequest($event->registration);
    }
}
