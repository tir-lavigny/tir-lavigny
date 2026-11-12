<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventChain;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EventChainUpdaterService
{
    /**
     * Propagates changed chain fields to the linked events.
     *
     * @param  EventChain  $chain  The updated chain.
     * @param  array<string,mixed>  $changedFields  Associative array of field => new value (chain fields only).
     * @param  string  $scope  'all' | 'from_date'
     * @param  Carbon|null  $fromDate  Required when $scope === 'from_date'.
     * @return array{updated_count: int, conflicted_event_ids: int[]}
     */
    public function update(
        EventChain $chain,
        array $changedFields,
        string $scope,
        ?Carbon $fromDate = null
    ): array {
        // Filter to only propagatable fields
        $changedFields = array_intersect_key($changedFields, array_flip(EventChain::PROPAGATABLE_FIELDS));

        if (empty($changedFields)) {
            return ['updated_count' => 0, 'conflicted_event_ids' => []];
        }

        $query = $chain->events()->with('customContent');

        if ($scope === 'from_date' && $fromDate !== null) {
            $query->where('start_datetime', '>=', $fromDate->startOfDay());
        }

        $events = $query->get();
        $conflictedEventIds = [];
        $updatedCount = 0;

        foreach ($events as $event) {
            $hasConflict = false;
            $pendingConflicts = $event->pending_conflict_fields ?? [];

            foreach ($changedFields as $field => $value) {
                if ($event->isFieldOverridden($field)) {
                    $hasConflict = true;
                    $pendingConflicts[] = $field;
                } else {
                    $this->applyFieldToEvent($event, $field, $value);
                }
            }

            $pendingConflicts = array_values(array_unique($pendingConflicts));

            if ($hasConflict) {
                $event->pending_conflict_fields = $pendingConflicts;
                $event->has_chain_conflict = true;
                $conflictedEventIds[] = $event->id;
            }

            $event->save();
            $updatedCount++;
        }

        Log::info('Event chain updated', [
            'event_chain_id' => $chain->id,
            'scope' => $scope,
            'updated_count' => $updatedCount,
            'conflict_count' => count($conflictedEventIds),
        ]);

        return [
            'updated_count' => $updatedCount,
            'conflicted_event_ids' => $conflictedEventIds,
        ];
    }

    /**
     * Resets all overridden / conflict fields on a single event back to the
     * chain's current values, clearing all override state.
     */
    public function resetToChain(Event $event, EventChain $chain): void
    {
        $event->loadMissing('customContent');

        foreach (EventChain::PROPAGATABLE_FIELDS as $field) {
            $chainValue = $this->getChainFieldValue($field, $chain);
            $this->applyFieldToEvent($event, $field, $chainValue);
        }

        $event->overridden_fields = [];
        $event->has_chain_conflict = false;
        $event->pending_conflict_fields = [];
        $event->save();

        Log::info('Event reset to chain values', [
            'event_id' => $event->id,
            'event_chain_id' => $chain->id,
        ]);
    }

    /**
     * Resolves a conflict on a single event field.
     *
     * @param  string  $resolution  'accept_chain' | 'keep_override'
     */
    public function resolveConflict(Event $event, string $field, string $resolution, EventChain $chain): void
    {
        if ($resolution === 'accept_chain') {
            $chainValue = $this->getChainFieldValue($field, $chain);
            $this->applyFieldToEvent($event, $field, $chainValue);
            $event->unmarkFieldOverridden($field);
        }
        // 'keep_override': keep the event's custom value as-is, just acknowledge the conflict

        // Remove this field from pending conflicts
        $pending = array_values(array_filter(
            $event->pending_conflict_fields ?? [],
            fn ($f) => $f !== $field
        ));
        $event->pending_conflict_fields = $pending;

        // Only clear the conflict flag when the chain value was accepted.
        // If the user kept their override the event intentionally diverges from
        // the chain, so has_chain_conflict remains true as a visual indicator.
        if ($resolution === 'accept_chain') {
            $event->has_chain_conflict = count($pending) > 0;
        }

        $event->save();

        Log::info('Event chain conflict resolved', [
            'event_id' => $event->id,
            'event_chain_id' => $chain->id,
            'field' => $field,
            'resolution' => $resolution,
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function applyFieldToEvent(Event $event, string $field, mixed $value): void
    {
        match ($field) {
            'title' => $event->title = $value,
            'location_id' => $event->location_id = $value,
            'content_id' => $event->content_id = $value,
            'start_time' => $event->start_datetime = Carbon::parse(
                $event->start_datetime->toDateString().' '.$value,
                'Europe/Zurich'
            )->utc(),
            'end_time' => $event->end_datetime = $value
                ? Carbon::parse(
                    ($event->end_datetime ?? $event->start_datetime)->toDateString().' '.$value,
                    'Europe/Zurich'
                )->utc()
                : null,
            'custom_body' => $this->applyCustomBody($event, $value),
            default => null,
        };
    }

    private function applyCustomBody(Event $event, ?string $body): void
    {
        if ($body) {
            $event->customContent()->updateOrCreate(
                ['event_id' => $event->id],
                ['body' => $body]
            );
        } else {
            $event->customContent()->delete();
        }
    }

    private function getChainFieldValue(string $field, EventChain $chain): mixed
    {
        return match ($field) {
            'start_time' => $chain->start_time,
            'end_time' => $chain->end_time,
            'custom_body' => $chain->custom_body,
            default => $chain->{$field},
        };
    }
}
