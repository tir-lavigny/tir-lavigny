<?php

namespace App\Actions\Ticket;

use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\OperatingDay;
use App\Models\Ticket;
use App\Services\TicketCalculator;
use App\Services\TicketService;
use Illuminate\Support\Facades\DB;

class CreateTicketAction
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function execute(StoreTicketRequest $request, OperatingDay $day, TicketCalculator $calculator): Ticket
    {
        return DB::transaction(function () use ($request, $day, $calculator) {
            return $this->ticketService->create($request, $day, $calculator);
        });
    }
}
