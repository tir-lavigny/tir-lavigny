<?php

namespace App\Events;

use App\Models\MemberRegistration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRegistrationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly MemberRegistration $registration) {}
}
