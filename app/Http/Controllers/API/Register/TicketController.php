<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $day = OperatingDay::findOrFail($request->input('operating_day_id'));

        if (! $day->isOpen()) {
            return response()->json(['message' => 'The operating day is closed.'], 422);
        }

        $ticket = DB::transaction(function () use ($request, $day) {
            $items = $request->input('items');
            $ticketItems = [];
            $total = 0;

            foreach ($items as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $unitPrice = $item['custom_unit_price'] ?? $product->unit_price;
                $quantity = $item['quantity'];
                $discount = 0;
                $voucherId = null;

                if (! empty($item['voucher_code'])) {
                    $voucher = Voucher::where('code', $item['voucher_code'])
                        ->whereNull('deleted_at')
                        ->firstOrFail();

                    $voucherId = $voucher->id;

                    if ($voucher->discount_percent !== null) {
                        $discount = $unitPrice * ($voucher->discount_percent / 100);
                    } else {
                        $discount = $voucher->discount_fixed;
                    }
                }

                $subtotal = ($unitPrice * $quantity) - $discount;
                $total += $subtotal;

                $ticketItems[] = [
                    'product_id' => $product->id,
                    'voucher_id' => $voucherId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            $ticket = Ticket::create([
                'total' => $total,
                'payment_method' => $request->input('payment_method'),
                'operating_day_id' => $day->id,
            ]);

            foreach ($ticketItems as $itemData) {
                $ticket->ticketItems()->create($itemData);
            }

            return $ticket;
        });

        $day->recalculateTotals();

        $ticket->load(['ticketItems.product', 'ticketItems.voucher']);

        return response()->json(['data' => $ticket], 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load(['ticketItems.product', 'ticketItems.voucher']);

        $allItems = $ticket->ticketItems()->withTrashed()
            ->with(['product', 'voucher'])
            ->get();

        $ticket->setRelation('ticketItems', $allItems);

        return response()->json(['data' => $ticket]);
    }
}
