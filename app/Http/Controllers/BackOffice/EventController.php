<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Event;
use App\Models\Location;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', null);
        $startBefore = $request->input('start_before', null);
        $endBefore = $request->input('end_before', null);
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

        if ($startBefore) {
            $query->where('start_datetime', '<=', $startBefore);
        }

        if ($endBefore) {
            $query->where('end_datetime', '<=', $endBefore);
        }

        $query->where('start_datetime', '>=', now()->startOfYear())
            ->orderBy('start_datetime');

        $data = $query->paginate(5)->withQueryString();

        return Inertia::render('backOffice/Events/Index', [
            'paginatedEvents' => $data,
            'filters' => [
                'search' => $search,
                'start_before' => $startBefore,
                'end_before' => $endBefore,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
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
    public function update(Request $request, string $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'start_datetime' => 'required|date',
            'end_datetime' => 'nullable|date|after_or_equal:start_datetime',
            'location_id' => 'nullable|exists:locations,id',
            'template_id' => 'nullable|exists:contents,id',
            'body' => 'nullable|string',
        ]);

        $event = Event::findOrFail($id);
        $event->title = $request->input('title');
        $event->start_datetime = $request->input('start_datetime');
        $event->end_datetime = $request->input('end_datetime');
        $event->location_id = $request->input('location_id');

        // Update content_id or set it to null
        $event->content_id = $request->input('template_id');

        // Handle custom content
        if ($request->input('body')) {
            $event->customContent()->updateOrCreate(
                ['event_id' => $event->id],
                ['body' => $request->input('body')]
            );
        } else {
            $event->customContent()->delete();
        }

        $event->save();

        return redirect()->route('bo.events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $event = Event::findOrFail($id);

        try {
            $event->delete();
        } catch (Exception $e) {
            return redirect()->back()->withErrors('Unable to delete event. Please try again later.');
        }

        return redirect()->back()->with('success', 'Event deleted successfully.');
    }
}
