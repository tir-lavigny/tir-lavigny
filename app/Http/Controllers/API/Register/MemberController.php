<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\MemberStatus;
use App\Models\Member;
use App\Models\OperatingDay;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    public function index(): JsonResponse
    {
        $members = Member::active()->get();

        return response()->json(['data' => MemberResource::collection($members)]);
    }

    public function vouchers(Member $member): JsonResponse
    {
        if ($member->status !== MemberStatus::ACTIVE) {
            return response()->json(['message' => 'Member is not active.'], 404);
        }

        $day = OperatingDay::whereDate('date', Carbon::today('Europe/Zurich'))
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
