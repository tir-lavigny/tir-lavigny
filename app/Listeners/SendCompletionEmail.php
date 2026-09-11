<?php

namespace App\Listeners;

use App\Events\MemberRegistrationCompleted;
use App\Services\MemberRegistrationPipelineService;

class SendCompletionEmail
{
    public function __construct(private readonly MemberRegistrationPipelineService $pipeline) {}

    public function handle(MemberRegistrationCompleted $event): void
    {
        $this->pipeline->retryFromStep($event->registration);
    }
}
