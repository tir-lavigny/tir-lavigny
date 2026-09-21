<?php

namespace App\Http\Controllers\BackOffice\Stock;

use App\Actions\Stock\CompleteStockCountAction;
use App\Actions\Stock\CreateStockCountAction;
use App\Enums\StockCountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Stock\CompleteStockCountRequest;
use App\Http\Requests\BackOffice\Stock\StoreStockCountRequest;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockCount;
use App\Models\StockLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage stock', only: ['create', 'store', 'complete']),
        ];
    }

    public function index(): Response
    {
        $counts = StockCount::with('stockLocation')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('backOffice/Stock/StockCounts/Index', [
            'counts' => $counts,
        ]);
    }

    public function create(): Response
    {
        $locations = StockLocation::where('active', true)->orderBy('name')->get();

        return Inertia::render('backOffice/Stock/StockCounts/Create', [
            'locations' => $locations,
        ]);
    }

    public function store(StoreStockCountRequest $request, CreateStockCountAction $action): RedirectResponse
    {
        $season = Season::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        $location = StockLocation::findOrFail((int) $request->validated('stock_location_id'));
        $products = Product::where('is_stockable', true)->get();

        $action->execute(
            season: $season,
            location: $location,
            type: $request->enum('type', StockCountType::class),
            products: $products,
        );

        return redirect()->route('bo.stock.counts.index')->with('success', 'Stock count created.');
    }

    public function show(StockCount $stockCount): Response
    {
        $stockCount->load(['stockLocation', 'stockCountLines.product']);

        return Inertia::render('backOffice/Stock/StockCounts/Show', [
            'count' => $stockCount,
        ]);
    }

    public function complete(StockCount $stockCount, CompleteStockCountRequest $request, CompleteStockCountAction $action): RedirectResponse
    {
        /** @var array<int, array{product_id: int, counted_quantity: int}> $lines */
        $lines = $request->validated('lines');

        foreach ($lines as $line) {
            $stockCount->stockCountLines()
                ->where('product_id', $line['product_id'])
                ->update(['counted_quantity' => $line['counted_quantity']]);
        }

        $action->execute($stockCount);

        return redirect()->route('bo.stock.counts.show', $stockCount)->with('success', 'Stock count completed.');
    }
}
