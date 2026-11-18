<?php

namespace App\Listeners;

use App\Events\MemberRegistrationEmailVerified;
use App\Services\MemberRegistrationPipelineService;

class SendValidationRequestEmail
{
    public function __construct(private readonly MemberRegistrationPipelineService $pipeline) {}

    public function handle(MemberRegistrationEmailVerified $event): void
    {
        $this->pipeline->sendValidationRequest($event->registration);
    }
}
