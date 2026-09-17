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

    public function create(StoreTicketRequest $request, OperatingDay $day, TicketCalculator $calculator): Ticket
    {
        $result = $calculator->compute($request->input('items'));

        $ticket = DB::transaction(function () use ($request, $day, $result) {
            $ticket = Ticket::create([
                'total' => $result['total'],
                'payment_method' => $request->input('payment_method'),
                'operating_day_id' => $day->id,
                'member_id' => $request->input('member_id'),
                'sold_at' => $this->localToUtc($request->input('sold_at')),
            ]);

            foreach ($result['items'] as $itemData) {
                $ticket->ticketItems()->create($itemData);
            }

            return $ticket;
        });

        $day->recalculateTotals();

        return $ticket;
    }

    public function refund(Ticket $ticket): void
    {
        $day = $ticket->operatingDay;

        $ticket->delete();

        $day->recalculateTotals();
    }
}
