<?php

namespace App\Events;

use App\Models\EventChain;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventChainCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly EventChain $chain) {}
}
