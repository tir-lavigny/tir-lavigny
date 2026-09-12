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

    public function show(Ticket $ticket)
    {
        return redirect()->route('bo.register.operating-days.show', [
            'operating_day' => $ticket->operating_day_id,
            'ticket' => $ticket->id,
        ]);
    }

    public function refund(Ticket $ticket)
    {
        $day = $ticket->operatingDay;

        $ticket->delete();

        $day->recalculateTotals();

        return redirect()->back()->with('success', 'Ticket refunded successfully.');
    }
}
