<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StoreSeasonRequest;
use App\Http\Requests\BackOffice\Register\UpdateSeasonRequest;
use App\Models\Member;
use App\Models\Season;
use App\Services\SeasonVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
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

    public function store(StoreSeasonRequest $request): RedirectResponse
    {
        Season::create($request->validated());

        return redirect()->route('bo.register.seasons.index')->with('success', 'Season created successfully.');
    }

    public function show(Season $season): Response
    {
        $season->load([
            'operatingDays' => function ($query) {
                $query->orderBy('date');
            },
            'cashMovements' => function ($query) {
                $query->orderByDesc('occurred_at')->orderByDesc('id');
            },
        ]);

        return Inertia::render('backOffice/Register/Seasons/Show', [
            'season' => $season,
            'balanceSummary' => $season->balanceSummary(),
        ]);
    }

    public function edit(Season $season): Response
    {
        return Inertia::render('backOffice/Register/Seasons/Edit', [
            'season' => $season,
            'closedDaysCount' => $season->operatingDays()->whereNotNull('closed_at')->count(),
        ]);
    }

    public function update(UpdateSeasonRequest $request, Season $season): RedirectResponse
    {
        $data = $request->validated();
        $initialBalanceChanged = array_key_exists('initial_balance', $data)
            && (float) $data['initial_balance'] !== (float) $season->initial_balance;

        DB::transaction(function () use ($season, $data, $initialBalanceChanged) {
            $season->update($data);

            if ($initialBalanceChanged) {
                $season->recalculateClosingBalances();
            }
        });

        return redirect()->route('bo.register.seasons.index')->with('success', 'Season updated successfully.');
    }

    public function destroy(Season $season): RedirectResponse
    {
        try {
            $season->delete();

            return redirect()->route('bo.register.seasons.index')->with('success', 'Saison supprimée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function previewBaseVouchers(Season $season, SeasonVoucherService $service): JsonResponse
    {
        $basePackage = $service->getBasePackage();
        $products = $service->resolveProducts($basePackage);
        $plan = $service->computePlan($season, $basePackage, $products);
        $totalMembers = Member::count();

        return response()->json([
            'plan' => $plan,
            'total_members' => $totalMembers,
        ]);
    }

    public function generateBaseVouchers(Season $season, SeasonVoucherService $service): RedirectResponse
    {
        $basePackage = $service->getBasePackage();
        $service->generateBaseVouchers($season, $basePackage);

        return redirect()->back()->with('success', 'Bons de base générés avec succès.');
    }
}
