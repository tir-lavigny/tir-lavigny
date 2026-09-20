<?php

namespace App\Http\Controllers\BackOffice\Stock;

use App\Actions\Stock\CreateStockLocationAction;
use App\Actions\Stock\UpdateStockLocationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Stock\StoreStockLocationRequest;
use App\Http\Requests\BackOffice\Stock\UpdateStockLocationRequest;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StockLocationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage stock', only: ['create', 'store', 'edit', 'update']),
        ];
    }

    public function index(): Response
    {
        $locations = StockLocation::orderBy('active', 'desc')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('backOffice/Stock/StockLocations/Index', [
            'locations' => $locations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Stock/StockLocations/Create');
    }

    public function store(StoreStockLocationRequest $request, CreateStockLocationAction $action): RedirectResponse
    {
        $location = $action->execute($request->validated());

        return redirect()->route('bo.stock.locations.show', $location)->with('success', 'Location created.');
    }

    public function show(StockLocation $stockLocation, StockService $stockService): Response
    {
        $stock = $stockService->getStockForLocation($stockLocation);

        $movements = StockMovement::where('stock_location_id', $stockLocation->id)
            ->with('product')
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('backOffice/Stock/StockLocations/Show', [
            'location' => $stockLocation,
            'stock' => $stock,
            'movements' => $movements,
        ]);
    }

    public function edit(StockLocation $stockLocation): Response
    {
        return Inertia::render('backOffice/Stock/StockLocations/Edit', [
            'location' => $stockLocation,
        ]);
    }

    public function update(StockLocation $stockLocation, UpdateStockLocationRequest $request, UpdateStockLocationAction $action): RedirectResponse
    {
        $action->execute($stockLocation, $request->validated());

        return redirect()->route('bo.stock.locations.show', $stockLocation)->with('success', 'Location updated.');
    }
}
