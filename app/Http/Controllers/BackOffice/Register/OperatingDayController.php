<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Actions\Ticket\CreateTicketAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StoreOperatingDayRequest;
use App\Http\Requests\BackOffice\Register\UpdateOperatingDayRequest;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Services\TicketCalculator;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\RedirectResponse;
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

    public function store(StoreOperatingDayRequest $request): RedirectResponse
    {
        $day = OperatingDay::create([
            'date' => $request->input('date'),
            'started_at' => Carbon::now('Europe/Zurich')->utc(),
            'season_id' => $request->input('season_id'),
        ]);

        return redirect()->route('bo.register.operating-days.show', $day)
            ->with('success', 'Journée ouverte avec succès.');
    }

    public function show(OperatingDay $operatingDay): Response
    {
        $operatingDay->load([
            'tickets' => fn ($q) => $q->withTrashed(),
            'tickets.member',
            'tickets.ticketItems' => fn ($q) => $q->withTrashed(),
            'tickets.ticketItems.product',
            'tickets.ticketItems.voucher',
            'tickets.stockLocation',
            'season',
        ]);

        $season = $operatingDay->season;
        $previousDay = $season->operatingDays()
            ->where('date', '<', $operatingDay->date->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $cashMovements = $season->cashMovements()
            ->whereDate('occurred_at', '<=', $operatingDay->date->toDateString())
            ->when($previousDay, fn ($query) => $query->whereDate('occurred_at', '>', $previousDay->date->toDateString()))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $openingBalance = round(
            (float) $operatingDay->closing_balance - (float) $operatingDay->total_cash - (float) $cashMovements->sum('amount'),
            2
        );

        return Inertia::render('backOffice/Register/OperatingDays/Show', [
            'operatingDay' => $operatingDay,
            'correctionProduct' => Product::where('name', 'Correction')->first(),
            'openingBalance' => $openingBalance,
            'cashMovements' => $cashMovements,
            'laterClosedDaysCount' => $season->operatingDays()
                ->whereDate('date', '>', $operatingDay->date->toDateString())
                ->whereNotNull('closed_at')
                ->count(),
        ]);
    }

    public function storeCorrection(
        Request $request,
        OperatingDay $operatingDay,
        CreateTicketAction $action,
        TicketCalculator $calculator,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'between:-99999999.99,99999999.99',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((float) $value === 0.0) {
                        $fail('Le montant ne peut pas être zéro.');
                    }
                },
            ],
        ]);

        $product = Product::where('name', 'Correction')->first();

        if ($product === null) {
            return back()->with('error', 'Le produit Correction est introuvable.');
        }

        $lastTicket = $operatingDay->tickets()->withTrashed()->latest('sold_at')->first();
        $location = $lastTicket !== null
            ? $lastTicket->stockLocation
            : StockLocation::where('active', true)->orderBy('id')->first();

        if ($location === null) {
            return back()->with('error', 'Aucun emplacement de stock actif.');
        }

        $action->execute([
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDay->id,
            'member_id' => null,
            'sold_at' => Carbon::now('Europe/Zurich')->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'custom_unit_price' => (float) $validated['amount'],
            ]],
        ], $operatingDay, $calculator);

        $season = $operatingDay->season;
        $season->recalculateClosingBalances();

        return redirect()->route('bo.register.operating-days.show', $operatingDay)
            ->with('success', 'Ticket correctif enregistré.');
    }

    public function update(UpdateOperatingDayRequest $request, OperatingDay $operatingDay): RedirectResponse
    {
        $status = $request->input('status');

        if ($status === 'closed') {
            if (! $operatingDay->isOpen()) {
                return back()->with('error', 'This operating day is already closed.');
            }

            $operatingDay->recalculateTotals();
            $operatingDay->update([
                'closed_at' => Carbon::now('Europe/Zurich')->utc(),
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
