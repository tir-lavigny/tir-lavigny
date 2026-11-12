<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventChain;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EventChainGeneratorService
{
    /**
     * Generates Event records for every occurrence defined by the chain.
     * Should be called once after a chain is created (via EventChainObserver).
     */
    public function generate(EventChain $chain): void
    {
        $dates = $chain->getOccurrenceDates();

        foreach ($dates as $date) {
            $this->createEventForDate($chain, $date);
        }

        Log::info('Event chain occurrences generated', [
            'event_chain_id' => $chain->id,
            'event_count' => count($dates),
        ]);
    }

    /**
     * Creates a single Event for a given occurrence date and chain config.
     */
    public function createEventForDate(EventChain $chain, Carbon $date): Event
    {
        $startDatetime = Carbon::parse($date->toDateString().' '.$chain->start_time, 'Europe/Zurich')->utc();
        $endDatetime = $chain->end_time
            ? Carbon::parse($date->toDateString().' '.$chain->end_time, 'Europe/Zurich')->utc()
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
