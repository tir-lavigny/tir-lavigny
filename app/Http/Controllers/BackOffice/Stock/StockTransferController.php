<?php

namespace App\Http\Controllers\BackOffice\Stock;

use App\Actions\Stock\CreateStockTransferAction;
use App\Exceptions\StockTransferInsufficientStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Stock\StoreStockTransferRequest;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage stock', only: ['create', 'store']),
        ];
    }

    public function index(): Response
    {
        $transfers = StockTransfer::with(['fromLocation', 'toLocation'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('backOffice/Stock/StockTransfers/Index', [
            'transfers' => $transfers,
        ]);
    }

    public function create(): Response
    {
        $locations = StockLocation::where('active', true)->orderBy('name')->get();
        $products = Product::where('is_stockable', true)->orderBy('name')->get();

        return Inertia::render('backOffice/Stock/StockTransfers/Create', [
            'locations' => $locations,
            'products' => $products,
        ]);
    }

    public function store(StockLocation $fromLocation, StoreStockTransferRequest $request, CreateStockTransferAction $action): RedirectResponse
    {
        $season = Season::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->firstOrFail();

        $toLocation = StockLocation::findOrFail((int) $request->validated('to_location_id'));

        /** @var array<int, array{product_id: int, quantity: int}> $rawItems */
        $rawItems = $request->validated('items');
        $items = collect($rawItems)->map(fn (array $item) => [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
        ]);

        try {
            $action->execute(
                season: $season,
                fromLocation: $fromLocation,
                toLocation: $toLocation,
                items: $items,
                reason: $request->validated('reason'),
            );
        } catch (StockTransferInsufficientStock $e) {
            $message = "Stock insuffisant pour \"{$e->product->name}\": disponible {$e->available}, demandé {$e->requested}.";

            return back()->withErrors(['items' => $message]);
        }

        return redirect()->route('bo.stock.transfers.index')->with('success', 'Stock transfer created.');
    }

    public function show(StockTransfer $stockTransfer): Response
    {
        $stockTransfer->load(['fromLocation', 'toLocation', 'stockTransferLines.product']);

        return Inertia::render('backOffice/Stock/StockTransfers/Show', [
            'transfer' => $stockTransfer,
        ]);
    }
}
