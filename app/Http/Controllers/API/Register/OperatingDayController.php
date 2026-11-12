<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Register\StoreDayRequest;
use App\Http\Requests\API\Register\UpdateDayRequest;
use App\Http\Resources\OperatingDayResource;
use App\Mail\OperatingDayClosed;
use App\Models\OperatingDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OperatingDayController extends Controller
{
    public function store(StoreDayRequest $request): JsonResponse
    {
        $day = OperatingDay::create([
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
            'started_at' => Carbon::now('Europe/Zurich')->utc(),
            'season_id' => $request->input('season_id'),
        ]);

        Log::info('Operating day opened', [
            'operating_day_id' => $day->id,
            'date' => $day->date->toDateString(),
            'season_id' => $day->season_id,
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

            DB::transaction(function () use ($operatingDay, $cash_remark) {
                $operatingDay->recalculateTotals();
                $operatingDay->update([
                    'closed_at' => Carbon::now('Europe/Zurich')->utc(),
                    'closing_balance' => $operatingDay->closing_balance,
                    'cash_remark' => $cash_remark,
                ]);
            });

            Log::info('Operating day closed', [
                'operating_day_id' => $operatingDay->id,
                'season_id' => $operatingDay->season_id,
                'date' => $operatingDay->date->toDateString(),
                'total_cash' => $operatingDay->total_cash,
                'total_twint' => $operatingDay->total_twint,
                'closing_balance' => $operatingDay->closing_balance,
                'cash_remark_present' => filled($cash_remark),
            ]);

            if (! empty($cash_remark)) {
                try {
                    Mail::to(User::getRegisterCloseOperatingDayRemarkEmails())->send(new OperatingDayClosed($operatingDay));
                } catch (\Throwable $e) {
                    report($e);

                    Log::error('Operating day close remark email failed', [
                        'operating_day_id' => $operatingDay->id,
                        'exception' => $e,
                    ]);
                }
            }
        } elseif ($status === 'open') {
            $operatingDay->update(['closed_at' => null, 'closing_balance' => null]);

            Log::warning('Operating day reopened', [
                'operating_day_id' => $operatingDay->id,
                'season_id' => $operatingDay->season_id,
                'date' => $operatingDay->date->toDateString(),
            ]);
        } else {
            $operatingDay->update($request->only(['cash_remark']));

            Log::info('Operating day cash remark updated', [
                'operating_day_id' => $operatingDay->id,
                'cash_remark_present' => filled($operatingDay->cash_remark),
            ]);
        }

        return response()->json(['data' => new OperatingDayResource($operatingDay->fresh())]);
    }

    public function current(): JsonResponse
    {
        $day = OperatingDay::whereDate('date', Carbon::today('Europe/Zurich'))
            ->with(['tickets' => fn ($q) => $q->withTrashed()], 'tickets.ticketItems.product', 'tickets.ticketItems.voucher')
            ->first();

        if (! $day) {
            return response()->json(['message' => 'No open operating day for today.'], 404);
        }

        return response()->json(['data' => new OperatingDayResource($day)]);
    }

    public function previousUnclosed(): JsonResponse
    {
        $day = OperatingDay::whereDate('date', '<', Carbon::today('Europe/Zurich'))
            ->whereNull('closed_at')
            ->with(['tickets' => fn ($q) => $q->withTrashed()], 'tickets.ticketItems.product', 'tickets.ticketItems.voucher')
            ->orderBy('date', 'desc')
            ->first();

        if (! $day) {
            return response()->json(['message' => 'No previous unclosed operating day found.'], 404);
        }

        return response()->json(['data' => new OperatingDayResource($day)]);
    }

    public function show(OperatingDay $operatingDay): JsonResponse
    {
        $operatingDay->setRelation('tickets', $operatingDay->tickets()->withTrashed()
            ->with(['ticketItems.product', 'ticketItems.voucher'])
            ->get());

        return response()->json(['data' => new OperatingDayResource($operatingDay)]);
    }
}
