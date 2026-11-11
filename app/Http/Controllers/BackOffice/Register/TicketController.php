<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Actions\Ticket\RefundTicketAction;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
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

    public function show(Ticket $ticket): RedirectResponse
    {
        return redirect()->route('bo.register.operating-days.show', [
            'operating_day' => $ticket->operating_day_id,
            'ticket' => $ticket->id,
        ]);
    }

    public function refund(Ticket $ticket, RefundTicketAction $action): RedirectResponse
    {
        if (! $ticket->operatingDay->isOpen()) {
            return back()->with('error', 'Impossible de rembourser un ticket d\'une journée fermée.');
        }

        $action->execute($ticket);

        return redirect()->back()->with('success', 'Ticket refunded successfully.');
    }
}
