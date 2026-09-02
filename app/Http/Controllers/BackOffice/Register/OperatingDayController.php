<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StoreOperatingDayRequest;
use App\Http\Requests\BackOffice\Register\UpdateOperatingDayRequest;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class OperatingDayController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage operating days', only: ['update']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = OperatingDay::with('season')->orderBy('date', 'desc');

        if ($seasonId = $request->input('season_id')) {
            $query->where('season_id', $seasonId);
        }

        $days = $query->paginate(15)->withQueryString();

        return Inertia::render('backOffice/Register/OperatingDays/Index', [
            'operatingDays' => $days,
            'filters' => ['season_id' => $seasonId ?? null],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Register/OperatingDays/Create', [
            'seasons' => Season::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreOperatingDayRequest $request)
    {
        $day = OperatingDay::create([
            'date' => $request->input('date'),
            'started_at' => Carbon::now(),
            'season_id' => $request->input('season_id'),
        ]);

        return redirect()->route('bo.register.operating-days.show', $day)
            ->with('success', 'Journée ouverte avec succès.');
    }

    public function show(OperatingDay $operatingDay): Response
    {
        $operatingDay->load(['tickets.ticketItems.product', 'tickets.ticketItems.voucher', 'season']);

        return Inertia::render('backOffice/Register/OperatingDays/Show', [
            'operatingDay' => $operatingDay,
            'correctionProduct' => Product::where('name', 'Correction')->first(),
        ]);
    }

    public function update(UpdateOperatingDayRequest $request, OperatingDay $operatingDay)
    {
        $status = $request->input('status');

        if ($status === 'closed') {
            if (! $operatingDay->isOpen()) {
                return redirect()->back()->withErrors(['status' => 'This operating day is already closed.']);
            }

            $operatingDay->recalculateTotals();
            $operatingDay->update([
                'closed_at' => Carbon::now(),
                'closing_balance' => $operatingDay->closing_balance,
            ]);

        } elseif ($status === 'open') {
            $operatingDay->update(['closed_at' => null, 'closing_balance' => null]);
        } else {
            $operatingDay->update($request->only(['cash_remark']));
        }

        return redirect()->route('bo.register.operating-days.show', $operatingDay)
            ->with('success', 'Operating day updated successfully.');
    }
}
