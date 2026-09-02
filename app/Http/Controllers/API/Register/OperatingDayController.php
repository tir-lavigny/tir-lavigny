<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Register\StoreDayRequest;
use App\Http\Requests\API\Register\UpdateDayRequest;
use App\Http\Resources\OperatingDayResource;
use App\Mail\OperatingDayClosed;
use App\Models\OperatingDay;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Log\log;

class OperatingDayController extends Controller
{
    public function store(StoreDayRequest $request): JsonResponse
    {
        $day = OperatingDay::create([
            'date' => Carbon::today()->toDateString(),
            'started_at' => Carbon::now(),
            'season_id' => $request->input('season_id'),
        ]);

        return response()->json(['data' => new OperatingDayResource($day)], 201);
    }

    public function update(UpdateDayRequest $request, OperatingDay $operatingDay): JsonResponse
    {
        $status = $request->input('status');
        $cash_remark = $request->input('cash_remark');

        if ($status === 'closed') {
            if (! $operatingDay->isOpen()) {
                return response()->json(['message' => 'This operating day is already closed.'], 422);
            }

            $operatingDay->recalculateTotals();
            $operatingDay->update([
                'closed_at' => Carbon::now(),
                'closing_balance' => $operatingDay->closing_balance,
                'cash_remark' => $cash_remark,
            ]);

            if (! empty($cash_remark)) {
                try {
                    Mail::to(env('APP_USER_EMAIL'))->send(new OperatingDayClosed($operatingDay));
                } catch (\Throwable $e) {
                    // Don't fail the close if email fails
                    log('error', ['exception' => $e->getMessage()]);
                }
            }
        } elseif ($status === 'open') {
            $operatingDay->update(['closed_at' => null, 'closing_balance' => null]);
        } else {
            $operatingDay->update($request->only(['cash_remark']));
        }

        return response()->json(['data' => new OperatingDayResource($operatingDay->fresh())]);
    }

    public function current(): JsonResponse
    {
        $day = OperatingDay::whereDate('date', Carbon::today())
            ->with('tickets.ticketItems.product', 'tickets.ticketItems.voucher')
            ->first();

        if (! $day) {
            return response()->json(['message' => 'No open operating day for today.'], 404);
        }

        return response()->json(['data' => new OperatingDayResource($day)]);
    }

    public function show(OperatingDay $operatingDay): JsonResponse
    {
        $operatingDay->load([
            'tickets.ticketItems.product',
            'tickets.ticketItems.voucher',
        ]);

        $operatingDay->setRelation('tickets', $operatingDay->tickets()->withTrashed()
            ->with(['ticketItems.product', 'ticketItems.voucher'])
            ->get());

        return response()->json(['data' => new OperatingDayResource($operatingDay)]);
    }
}
