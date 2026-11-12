<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\StoreEventRequest;
use App\Http\Requests\BackOffice\UpdateEventRequest;
use App\Models\Content;
use App\Models\Event;
use App\Models\Location;
use App\Services\EventService;
use App\Traits\ConvertsLocalDatetimes;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller implements HasMiddleware
{
    use ConvertsLocalDatetimes;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:create event', only: ['create', 'store']),
            new Middleware('permission:edit event', only: ['edit', 'update']),
            new Middleware('permission:delete event', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search', null);
        $past = $request->boolean('past', false);
        $query = Event::query();
        $query->with('location');

        if ($search) {
            $search = Str::lower($search);
            $query->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%{$search}%");
                $query->orWhereHas('location', function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                });
            });
        }

        if ($past) {
            $query->where('start_datetime', '<', now()->startOfDay());
        } else {
            $query->where('start_datetime', '>=', now()->startOfDay());
        }

        $query->orderBy('start_datetime');

        $data = $query->paginate(5)->withQueryString();

        return Inertia::render('backOffice/Events/Index', [
            'paginatedEvents' => $data,
            'filters' => [
                'search' => $search,
                'past' => $past,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $templates = Content::all();
        $locations = Location::all();

        return Inertia::render('backOffice/Events/Create', [
            'templates' => $templates,
            'locations' => $locations,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = new Event;
        $event->title = $request->input('title');
        $event->start_datetime = $this->localToUtc($request->input('start_datetime'));
        $event->end_datetime = $this->localToUtc($request->input('end_datetime'));
        $event->location_id = $request->input('location_id');
        $event->content_id = $request->input('template_id');
        $event->save();

        if ($request->input('body')) {
            $event->customContent()->create([
                'event_id' => $event->id,
                'body' => $request->input('body'),
            ]);
        }

        return redirect()->route('bo.events.show', $event->id)->with('success', 'Event created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): Response
    {
        $event = Event::with('location')
            ->where('id', $id)
            ->firstOrFail();
        $content = $event->getEffectiveContent();

        return Inertia::render('backOffice/Events/Show', [
            'event' => $event,
            'content' => $content,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): Response
    {
        $event = Event::with('location')
            ->where('id', $id)
            ->firstOrFail();

        $content = $event->getEffectiveContent();
        $contentSource = null;
        if ($event->customContent) {
            $contentSource = 'custom';
        } elseif ($event->content) {
            $contentSource = 'template_'.$event->content->id;
        }

        $templates = Content::all();
        $locations = Location::all();

        return Inertia::render('backOffice/Events/Edit', [
            'event' => $event,
            'content' => $content,
            'contentSource' => $contentSource,
            'templates' => $templates,
            'locations' => $locations,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, string $id): RedirectResponse
    {
        $event = Event::with('eventChain')->findOrFail($id);
        $event->title = $request->input('title');
        $event->start_datetime = $this->localToUtc($request->input('start_datetime'));
        $event->end_datetime = $this->localToUtc($request->input('end_datetime'));
        $event->location_id = $request->input('location_id');
        $event->content_id = $request->input('template_id');

        if ($request->input('body')) {
            $event->customContent()->updateOrCreate(
                ['event_id' => $event->id],
                ['body' => $request->input('body')]
            );
        } else {
            $event->customContent()->delete();
        }

        if ($event->eventChain) {
            $eventService = app(EventService::class);
            $changedFields = $eventService->compareEventToChain($event, $request->all());
            $eventService->applyChainOverrides($event, $changedFields);
        }

        $event->save();

        return redirect()->route('bo.events.show', $event->id)->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $event = Event::findOrFail($id);

        try {
            $event->delete();
        } catch (Exception $e) {
            report($e);

            Log::error('Event deletion failed', [
                'event_id' => $event->id,
                'exception' => $e,
            ]);

            return back()->with('error', 'Unable to delete event. Please try again later.');
        }

        return redirect()->route('bo.events.index')->with('success', 'Event deleted successfully.');
    }
}
