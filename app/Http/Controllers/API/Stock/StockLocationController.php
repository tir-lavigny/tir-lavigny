<?php

namespace App\Http\Controllers\API\Stock;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockLocationResource;
use App\Models\StockLocation;
use Illuminate\Http\JsonResponse;

class StockLocationController extends Controller
{
    public function index(): JsonResponse
    {
        $locations = StockLocation::where('active', true)->get();

        return response()->json(['data' => StockLocationResource::collection($locations)]);
    }
}
