<?php

namespace App\Listeners;

use App\Events\EventChainCreated;
use App\Services\EventChainGeneratorService;

class GenerateEventChainOccurrences
{
    public function __construct(private readonly EventChainGeneratorService $generator) {}

    public function handle(EventChainCreated $event): void
    {
        $this->generator->generate($event->chain);
    }
}
