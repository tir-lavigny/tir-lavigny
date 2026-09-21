<?php

namespace App\Http\Controllers\BackOffice\Stock;

use App\Actions\Stock\RecordStockLossAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Stock\StoreStockLossRequest;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StockLossController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage stock', only: ['create', 'store']),
        ];
    }

    public function index(): Response
    {
        $losses = StockMovement::where('type', 'loss')
            ->with(['stockLocation', 'product'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('backOffice/Stock/StockLosses/Index', [
            'losses' => $losses,
        ]);
    }

    public function create(): Response
    {
        $locations = StockLocation::where('active', true)->orderBy('name')->get();
        $products = Product::where('is_stockable', true)->orderBy('name')->get();

        return Inertia::render('backOffice/Stock/StockLosses/Create', [
            'locations' => $locations,
            'products' => $products,
        ]);
    }

    public function store(StockLocation $location, StoreStockLossRequest $request, RecordStockLossAction $action): RedirectResponse
    {
        $season = Season::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        $product = Product::findOrFail((int) $request->validated('product_id'));

        $action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: $request->validated('quantity'),
            reason: $request->validated('reason'),
        );

        return redirect()->route('bo.stock.losses.index')->with('success', 'Stock loss recorded.');
    }

    public function show(StockMovement $stockMovement): Response
    {
        $stockMovement->load(['stockLocation', 'product']);

        return Inertia::render('backOffice/Stock/StockLosses/Show', [
            'loss' => $stockMovement,
        ]);
    }
}
