<?php

namespace App\Services;

use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\OperatingDay;
use App\Models\Ticket;
use App\Traits\ConvertsLocalDatetimes;
use Illuminate\Support\Facades\DB;

class TicketService
{
    use ConvertsLocalDatetimes;

    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function create(StoreTicketRequest $request, OperatingDay $day, TicketCalculator $calculator): Ticket
    {
        return DB::transaction(function () use ($request, $day, $calculator) {
            $result = $calculator->compute($request->input('items'));

            $ticket = Ticket::create([
                'total' => $result['total'],
                'payment_method' => $request->input('payment_method'),
                'operating_day_id' => $day->id,
                'member_id' => $request->input('member_id'),
                'sold_at' => $this->localToUtc($request->input('sold_at')),
                'stock_location_id' => $request->input('stock_location_id'),
            ]);

            foreach ($result['items'] as $itemData) {
                $ticket->ticketItems()->create($itemData);
            }

            $this->stockService->deductForTicket($ticket);

            $day->recalculateTotals();

            return $ticket;
        });
    }

    public function refund(Ticket $ticket): void
    {
        abort_if($ticket->trashed(), 422, 'Ticket is already refunded.');

        $day = $ticket->operatingDay;

        DB::transaction(function () use ($ticket, $day) {
            $this->stockService->reverseForTicket($ticket);

            $ticket->delete();

            $day->recalculateTotals();
        });
    }
}
