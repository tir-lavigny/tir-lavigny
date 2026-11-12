<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Event;
use App\Models\EventChain;
use App\Models\Location;
use App\Services\EventChainGeneratorService;
use App\Services\EventChainUpdaterService;
use App\Traits\SearchesAccentInsensitively;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EventChainController extends Controller implements HasMiddleware
{
    use SearchesAccentInsensitively;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:create event chain', only: ['create', 'store']),
            new Middleware('permission:edit event chain', only: ['edit', 'update', 'resolveConflict', 'resetToChain', 'detachEvent']),
            new Middleware('permission:delete event chain', only: ['destroy']),
        ];
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = EventChain::with('location')->withCount('events');
        $this->whereAccentInsensitiveLike($query, ['title'], $search);

        $chains = $query
            ->orderBy('start_date')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('backOffice/EventChains/Index', [
            'paginatedChains' => $chains,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/EventChains/Create', [
            'templates' => Content::all(),
            'locations' => Location::all(),
        ]);
    }

    public function store(Request $request, EventChainGeneratorService $generator): RedirectResponse
    {
        $data = $request->validate($this->chainRules());

        // The observer will call $generator->generate() automatically after create.
        EventChain::create($data);

        return redirect()
            ->route('bo.event-chains.index')
            ->with('success', 'Recurring event chain created and events generated successfully.');
    }

    public function show(EventChain $eventChain): Response
    {
        $eventChain->load('location', 'content');

        $events = $eventChain->events()
            ->with(['location', 'customContent'])
            ->orderBy('start_datetime')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('backOffice/EventChains/Show', [
            'chain' => $eventChain,
            'paginatedEvents' => $events,
        ]);
    }

    public function edit(EventChain $eventChain): Response
    {
        return Inertia::render('backOffice/EventChains/Edit', [
            'chain' => $eventChain,
            'templates' => Content::all(),
            'locations' => Location::all(),
        ]);
    }

    public function update(Request $request, EventChain $eventChain, EventChainUpdaterService $updater): RedirectResponse
    {
        $data = $request->validate(array_merge($this->chainRules(), [
            'update_scope' => ['required', 'in:all,from_date'],
            'from_date' => ['required_if:update_scope,from_date', 'nullable', 'date'],
        ]));

        $scope = $data['update_scope'];
        $fromDate = isset($data['from_date']) ? Carbon::parse($data['from_date']) : null;

        // Determine which propagatable fields actually changed
        $changedFields = [];
        foreach (EventChain::PROPAGATABLE_FIELDS as $field) {
            if (array_key_exists($field, $data) && $data[$field] != $eventChain->{$field}) {
                $changedFields[$field] = $data[$field];
            }
        }

        // Persist chain record (except scope fields)
        $chainData = collect($data)->except(['update_scope', 'from_date'])->toArray();
        $eventChain->update($chainData);

        // Propagate to events
        $result = $updater->update($eventChain, $changedFields, $scope, $fromDate);

        $message = "Chain updated. {$result['updated_count']} event(s) affected.";
        if (! empty($result['conflicted_event_ids'])) {
            $count = count($result['conflicted_event_ids']);
            $message .= " {$count} event(s) have conflicts that need manual review.";
        }

        return redirect()
            ->route('bo.event-chains.show', $eventChain)
            ->with('success', $message);
    }

    public function destroy(EventChain $eventChain): RedirectResponse
    {
        // Events will be detached (event_chain_id set to null) via nullOnDelete FK constraint.
        // Delete the now-orphaned chain events explicitly first, or keep them standalone.
        // Per spec: deleting a chain deletes ALL its events.
        $eventCount = $eventChain->events()->count();

        $eventChain->events()->delete();
        $eventChain->delete();

        Log::info('Event chain deleted', [
            'event_chain_id' => $eventChain->id,
            'deleted_event_count' => $eventCount,
        ]);

        return redirect()
            ->route('bo.event-chains.index')
            ->with('success', 'Chain and all its events deleted.');
    }

    // ─── Additional actions ───────────────────────────────────────────────────

    /**
     * Resolve a chain-vs-override conflict on a single event field.
     * POST /back-office/event-chains/{eventChain}/events/{event}/resolve-conflict
     */
    public function resolveConflict(
        Request $request,
        EventChain $eventChain,
        Event $event,
        EventChainUpdaterService $updater
    ): RedirectResponse {
        $data = $request->validate([
            'field' => ['required', 'string', 'in:'.implode(',', EventChain::PROPAGATABLE_FIELDS)],
            'resolution' => ['required', 'in:accept_chain,keep_override'],
        ]);

        abort_unless($event->event_chain_id === $eventChain->id, 404);

        $updater->resolveConflict($event, $data['field'], $data['resolution'], $eventChain);

        return redirect()
            ->route('bo.event-chains.show', $eventChain)
            ->with('success', 'Conflict resolved.');
    }

    /**
     * Resets a single event back to all of the chain's current values,
     * discarding all manual overrides.
     * POST /back-office/event-chains/{eventChain}/events/{event}/reset-to-chain
     */
    public function resetToChain(
        EventChain $eventChain,
        Event $event,
        EventChainUpdaterService $updater
    ): RedirectResponse {
        abort_unless($event->event_chain_id === $eventChain->id, 404);

        $updater->resetToChain($event, $eventChain);

        return redirect()
            ->route('bo.event-chains.show', $eventChain)
            ->with('success', 'Event reset to chain values.');
    }

    /**
     * Detach a single event from its chain (makes it standalone).
     * POST /back-office/events/{event}/detach
     */
    public function detachEvent(Event $event): RedirectResponse
    {
        $event->detachFromChain();

        return redirect()
            ->back()
            ->with('success', 'Event detached from chain.');
    }

    // ─── Validation rules ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function chainRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'recurrence_type' => ['required', 'in:'.implode(',', EventChain::RECURRENCE_TYPES)],
            'interval' => ['nullable', 'integer', 'min:1'],
            'interval_unit' => ['nullable', 'in:days,weeks'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s', 'after:start_time'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'content_id' => ['nullable', 'exists:contents,id'],
            'custom_body' => ['nullable', 'string'],
        ];
    }
}
