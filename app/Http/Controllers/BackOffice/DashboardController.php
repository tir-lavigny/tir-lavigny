<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $activities = ActivityLog::where('user_id', Auth::id())
            ->where('model', '!=', 'App\Models\StockCountLine')
            ->where('model', '!=', 'App\Models\StockMovement')
            ->where('model', '!=', 'App\Models\TicketItem')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $eventsPerYear = Event::select('start_datetime')
            ->get()
            ->map(fn (Event $event) => $event->start_datetime->format('Y'))
            ->countBy()
            ->map(fn (int $count, string $year) => ['year' => $year, 'count' => $count])
            ->values()
            ->sortByDesc('year')
            ->values();

        $currentSeason = Season::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        $dailySales = $currentSeason
            ? $currentSeason->operatingDays()
                ->selectRaw('date, sum(total_cash_cents) as cash_cents, sum(total_twint_cents) as twint_cents')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn (OperatingDay $day): array => [
                    'date' => $day->date,
                    'cash' => Money::fromCents((int) $day->getAttribute('cash_cents')),
                    'twint' => Money::fromCents((int) $day->getAttribute('twint_cents')),
                ])
                ->values()
            : [];

        return Inertia::render('backOffice/Dashboard', [
            'activities' => $activities,
            'eventsPerYear' => $eventsPerYear,
            'dailySales' => $dailySales,
        ]);
    }
}
