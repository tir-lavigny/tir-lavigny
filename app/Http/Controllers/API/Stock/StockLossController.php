<?php

namespace App\Http\Controllers\API\Stock;

use App\Actions\Stock\RecordStockLossAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Stock\StoreStockLossRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockLossController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:record stock loss', only: ['store']),
        ];
    }

    public function index(StockLocation $stockLocation, OperatingDay $operatingDay): JsonResponse
    {
        if (! $operatingDay->isOpen()) {
            return response()->json(['data' => []]);
        }

        if (! $stockLocation->exists) {
            return response()->json(['data' => []]);
        }

        $losses = StockMovement::where('type', 'loss')
            ->whereDate('created_at', '>=', $operatingDay->date->copy()->startOfDay())
            ->whereDate('created_at', '<=', $operatingDay->date->copy()->endOfDay())
            ->where('stock_location_id', $stockLocation->id)
            ->with(['stockLocation', 'product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => StockMovementResource::collection($losses)]);
    }

    public function store(StoreStockLossRequest $request, RecordStockLossAction $action): JsonResponse
    {
        $operatingDay = OperatingDay::findOrFail((int) $request->validated('operating_day_id'));
        $stockLocation = StockLocation::findOrFail((int) $request->validated('stock_location_id'));

        if (! $operatingDay->isOpen()) {
            return response()->json(['data' => []], 400);
        }

        $season = $operatingDay->season;
        $product = Product::findOrFail((int) $request->validated('product_id'));

        $stockLoss = $action->execute(
            season: $season,
            location: $stockLocation,
            product: $product,
            quantity: $request->validated('quantity'),
            reason: $request->validated('reason'),
        );

        return response()->json(['data' => new StockMovementResource($stockLoss)], 201);
    }
}
