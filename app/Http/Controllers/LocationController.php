<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class LocationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:create location', only: ['store']),
            new Middleware('permission:edit location', only: ['update']),
            new Middleware('permission:delete location', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $locations = Location::orderBy('name')->get();

        return Inertia::render('backOffice/Locations', [
            'locations' => $locations,
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
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $location = Location::where('name', $request->name)->first();
        if ($location) {
            return back()->with('error', 'Ce lieu existe déjà.');
        }

        Location::create($request->only(['name', 'address', 'latitude', 'longitude']));

        return redirect()->back()->with('success', 'Lieu créé avec succès');
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255,|unique:locations,name,'.$id,
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $location = Location::findOrFail($id);
        $location->update($request->only(['name', 'address', 'latitude', 'longitude']));

        return redirect()->back()->with('success', 'Lieu mis à jour avec succès');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $location = Location::findOrFail($id);
        if ($location->events()->exists()) {
            return back()->with('error', 'Impossible de supprimer ce lieu car il est associé à des événements.');
        }

        $location->delete();

        return redirect()->back()->with('success', 'Lieu supprimé avec succès');
    }
}
