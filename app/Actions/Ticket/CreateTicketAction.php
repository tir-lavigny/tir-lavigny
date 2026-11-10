<?php

namespace App\Actions\Ticket;

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, OperatingDay $day, TicketCalculator $calculator): Ticket
    {
        return DB::transaction(function () use ($data, $day, $calculator) {
            return $this->ticketService->create($data, $day, $calculator);
        });
    }
}
