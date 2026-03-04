<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventChain;
use Carbon\Carbon;

class EventChainGeneratorService
{
    /**
     * Generates Event records for every occurrence defined by the chain.
     * Should be called once after a chain is created (via EventChainObserver).
     */
    public function generate(EventChain $chain): void
    {
        foreach ($chain->getOccurrenceDates() as $date) {
            $this->createEventForDate($chain, $date);
        }
    }

    /**
     * Creates a single Event for a given occurrence date and chain config.
     */
    public function createEventForDate(EventChain $chain, Carbon $date): Event
    {
        $startDatetime = $date->copy()->setTimeFromTimeString($chain->start_time);
        $endDatetime = $chain->end_time
            ? $date->copy()->setTimeFromTimeString($chain->end_time)
            : null;

        /** @var Event $event */
        $event = Event::create([
            'title' => $chain->title,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'location_id' => $chain->location_id,
            'content_id' => $chain->content_id,
            'event_chain_id' => $chain->id,
            'overridden_fields' => [],
            'has_chain_conflict' => false,
            'pending_conflict_fields' => [],
        ]);

        if ($chain->custom_body) {
            $event->customContent()->create(['body' => $chain->custom_body]);
        }

        return $event;
    }
}
