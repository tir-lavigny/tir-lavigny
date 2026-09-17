<?php

namespace App\Actions\Ticket;

use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Support\Facades\DB;

class RefundTicketAction
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function execute(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket) {
            $this->ticketService->refund($ticket);
        });
    }
}
