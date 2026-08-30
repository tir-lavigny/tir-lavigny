<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\OperatingDay;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    public function vouchers(Member $member): JsonResponse
    {
        $day = OperatingDay::whereDate('date', Carbon::today())
            ->whereNull('closed_at')
            ->first();

        if (! $day) {
            return response()->json(['message' => 'No open operating day for today.'], 404);
        }

        $vouchers = $member->vouchers()
            ->where('season_id', $day->season_id)
            ->whereDoesntHave('ticketItems')
            ->with('product')
            ->get();

        return response()->json(['data' => $vouchers]);
    }
}
