<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StoreSeasonRequest;
use App\Http\Requests\BackOffice\Register\UpdateSeasonRequest;
use App\Models\Season;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage seasons', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(): Response
    {
        $seasons = Season::orderBy('start_date', 'desc')->paginate(15);

        return Inertia::render('backOffice/Register/Seasons/Index', [
            'seasons' => $seasons,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Register/Seasons/Create');
    }

    public function store(StoreSeasonRequest $request)
    {
        Season::create($request->validated());

        return redirect()->route('bo.register.seasons.index')->with('success', 'Season created successfully.');
    }

    public function show(Season $season): Response
    {
        $season->load(['operatingDays' => function ($query) {
            $query->orderBy('date');
        }]);

        return Inertia::render('backOffice/Register/Seasons/Show', [
            'season' => $season,
        ]);
    }

    public function edit(Season $season): Response
    {
        return Inertia::render('backOffice/Register/Seasons/Edit', [
            'season' => $season,
        ]);
    }

    public function update(UpdateSeasonRequest $request, Season $season)
    {
        $season->update($request->validated());

        return redirect()->route('bo.register.seasons.index')->with('success', 'Season updated successfully.');
    }

    public function destroy(Season $season)
    {
        try {
            $season->delete();

            return redirect()->route('bo.register.seasons.index')->with('success', 'Saison supprimée.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['delete' => $e->getMessage()]);
        }
    }
}
