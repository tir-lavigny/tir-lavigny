<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Models\OperatingDay;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view reports'),
        ];
    }

    public function index(): Response
    {
        $seasons = Season::withCount('operatingDays')
            ->orderByDesc('start_date')
            ->get();

        return Inertia::render('backOffice/Register/Reports/Index', [
            'seasons' => $seasons,
        ]);
    }

    public function dayDetail(OperatingDay $operatingDay): Response
    {
        $operatingDay->setRelation('tickets', $operatingDay->tickets()->withTrashed()
            ->with(['ticketItems' => fn ($q) => $q->withTrashed()->with(['product', 'voucher'])])
            ->get());

        return Inertia::render('backOffice/Register/Reports/DayDetail', [
            'operatingDay' => $operatingDay,
        ]);
    }

    public function daySummary(OperatingDay $operatingDay): Response
    {
        $operatingDay->setRelation('tickets', $operatingDay->tickets()->withTrashed()->get());

        return Inertia::render('backOffice/Register/Reports/DaySummary', [
            'operatingDay' => $operatingDay,
        ]);
    }

    public function period(Request $request): Response
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $detail = $request->input('detail', 'balance');

        $query = OperatingDay::query()->orderBy('date');

        if ($from) {
            $query->where('date', '>=', $from);
        }
        if ($to) {
            $query->where('date', '<=', $to);
        }

        $days = $query->get();

        return Inertia::render('backOffice/Register/Reports/Period', [
            'operatingDays' => $days,
            'filters' => ['from' => $from, 'to' => $to, 'detail' => $detail],
        ]);
    }

    public function season(Request $request, Season $season): Response
    {
        $detail = $request->input('detail', 'balance');

        $days = match ($detail) {
            'items' => $season->operatingDays()
                ->with(['tickets.ticketItems.product', 'tickets.ticketItems.voucher'])
                ->orderBy('date')
                ->get(),
            'tickets' => $season->operatingDays()
                ->with(['tickets'])
                ->orderBy('date')
                ->get(),
            default => $season->operatingDays()->orderBy('date')->get(),
        };

        return Inertia::render('backOffice/Register/Reports/Season', [
            'season' => $season,
            'operatingDays' => $days,
            'filters' => ['detail' => $detail],
        ]);
    }
}
