<?php

namespace App\Services;

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, OperatingDay $day, TicketCalculator $calculator): Ticket
    {
        return DB::transaction(function () use ($data, $day, $calculator) {
            /** @var array<int, array{product_id: int, quantity: int, custom_unit_price?: float, voucher_ids?: array<int, int|null>}> $items */
            $items = $data['items'];

            $result = $calculator->compute($items);

            $ticket = Ticket::create([
                'total' => $result['total'],
                'payment_method' => $data['payment_method'],
                'operating_day_id' => $day->id,
                'member_id' => $data['member_id'] ?? null,
                'sold_at' => $this->localToUtc($data['sold_at']),
                'stock_location_id' => $data['stock_location_id'],
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
            $day->season->recalculateClosingBalances();
        });
    }
}
