<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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

        return Inertia::render('backOffice/Dashboard', [
            'activities' => $activities,
        ]);
    }
}
