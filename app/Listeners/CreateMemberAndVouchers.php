<?php

namespace App\Listeners;

use App\Events\MemberRegistrationDavCreated;
use App\Services\MemberRegistrationPipelineService;

class CreateMemberAndVouchers
{
    public function __construct(private readonly MemberRegistrationPipelineService $pipeline) {}

    public function handle(MemberRegistrationDavCreated $event): void
    {
        $this->pipeline->retryFromStep($event->registration);
    }
}
