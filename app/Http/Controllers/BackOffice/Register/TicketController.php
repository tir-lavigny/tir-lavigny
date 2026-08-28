<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TicketController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:refund ticket', only: ['refund']),
        ];
    }

    public function refund(Ticket $ticket)
    {
        $day = $ticket->operatingDay;

        $ticket->delete();

        $day->recalculateTotals();

        return redirect()->back()->with('success', 'Ticket refunded successfully.');
    }
}
