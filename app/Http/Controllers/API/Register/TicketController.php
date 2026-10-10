<?php

namespace App\Http\Controllers\API\Register;

use App\Actions\Ticket\CreateTicketAction;
use App\Actions\Ticket\RefundTicketAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Register\StoreTicketPreviewRequest;
use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\OperatingDay;
use App\Models\Ticket;
use App\Services\TicketCalculator;
use Illuminate\Http\JsonResponse;
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

    public function store(CreateTicketAction $action, StoreTicketRequest $request, TicketCalculator $calculator): JsonResponse
    {
        $day = OperatingDay::findOrFail($request->input('operating_day_id'));
        assert($day instanceof OperatingDay);

        if (! $day->isOpen()) {
            return response()->json(['message' => 'The operating day is closed.'], 422);
        }

        if ($day->date < now('Europe/Zurich')->toDateString()) {
            return response()->json(['message' => 'The operating day is in the past. Can\'t create tickets for it.'], 422);
        }

        $ticket = $action->execute($request, $day, $calculator);

        $ticket->load(['ticketItems.product', 'ticketItems.voucher']);

        return response()->json(['data' => $ticket], 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $allItems = $ticket->ticketItems()->withTrashed()
            ->with(['product', 'voucher'])
            ->get();

        $ticket->setRelation('ticketItems', $allItems);

        return response()->json(['data' => $ticket]);
    }

    public function preview(StoreTicketPreviewRequest $request, TicketCalculator $calculator): JsonResponse
    {
        $day = OperatingDay::findOrFail($request->input('operating_day_id'));
        assert($day instanceof OperatingDay);

        if (! $day->isOpen()) {
            return response()->json(['message' => 'The operating day is closed.'], 422);
        }

        $result = $calculator->compute($request->input('items'));

        return response()->json([
            'total' => $result['total'],
            'items' => $result['items'],
        ]);
    }

    public function refund(RefundTicketAction $action, Ticket $ticket): JsonResponse
    {
        if ($ticket->trashed()) {
            return response()->json(['message' => 'Ticket is already refunded.'], 422);
        }

        $day = $ticket->operatingDay;
        assert($day instanceof OperatingDay);

        if (! $day->isOpen()) {
            return response()->json(['message' => 'The operating day is closed.'], 422);
        }

        $action->execute($ticket);

        return response()->json(['message' => 'Ticket refunded successfully.']);
    }
}
