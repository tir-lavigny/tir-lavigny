<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventChain;
use Carbon\Carbon;

class EventService
{
    /**
     * Compare an event's current field values against its chain values.
     * Returns an array of field names that differ.
     *
     * @return array<string, array{event_value: mixed, chain_value: mixed}>
     */
    public function compareEventToChain(Event $event, array $data, ?EventChain $chain = null): array
    {
        $chain = $chain ?? $event->eventChain;

        if (! $chain) {
            return [];
        }

        $body = $data['body'] ?? null;

        $comparisons = [
            'title' => [$event->title, $chain->title],
            'location_id' => [$event->location_id, $chain->location_id],
            'content_id' => [$event->content_id, $chain->content_id],
            'start_time' => [
                Carbon::parse($event->start_datetime)->setTimezone('Europe/Zurich')->format('H:i:s'),
                Carbon::parse($chain->start_time)->format('H:i:s'),
            ],
            'end_time' => [
                $event->end_datetime ? Carbon::parse($event->end_datetime)->setTimezone('Europe/Zurich')->format('H:i:s') : null,
                $chain->end_time ? Carbon::parse($chain->end_time)->format('H:i:s') : null,
            ],
            'custom_body' => [$body, $chain->custom_body],
        ];

        $changed = [];

        foreach ($comparisons as $field => [$eventValue, $chainValue]) {
            if ($eventValue != $chainValue) {
                $changed[$field] = ['event_value' => $eventValue, 'chain_value' => $chainValue];
            }
        }

        return $changed;
    }

    /**
     * Mark or unmark fields as overridden on an event based on chain comparison.
     */
    public function applyChainOverrides(Event $event, array $changedFields): void
    {
        foreach ($changedFields as $field => $values) {
            $event->markFieldOverridden($field);
        }

        $chain = $event->eventChain;

        if (! $chain) {
            return;
        }

        $allFields = EventChain::PROPAGATABLE_FIELDS;

        foreach ($allFields as $field) {
            if (! isset($changedFields[$field])) {
                $event->unmarkFieldOverridden($field);
            }
        }
    }
}
