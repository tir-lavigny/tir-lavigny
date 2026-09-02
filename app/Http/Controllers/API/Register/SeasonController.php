<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeasonResource;
use App\Models\Season;
use Illuminate\Http\JsonResponse;

class SeasonController extends Controller
{
    public function getCurrentSeason(): JsonResponse
    {
        $season = Season::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (! $season) {
            return response()->json(['data' => null], 404);
        }

        return response()->json(['data' => new SeasonResource($season)]);
    }
}
