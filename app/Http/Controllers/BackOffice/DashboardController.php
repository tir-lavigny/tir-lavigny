<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Season;
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

        $eventsPerYear = Event::selectRaw('strftime(\'%Y\', start_datetime) as year, count(*) as count')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get();

        $currentSeason = Season::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        $dailySales = $currentSeason
            ? $currentSeason->operatingDays()
                ->selectRaw('date, sum(total_cash) as cash, sum(total_twint) as twint')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
            : [];

        return Inertia::render('backOffice/Dashboard', [
            'activities' => $activities,
            'eventsPerYear' => $eventsPerYear,
            'dailySales' => $dailySales,
        ]);
    }
}
