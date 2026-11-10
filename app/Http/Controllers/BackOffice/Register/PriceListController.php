<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StorePriceListRequest;
use App\Http\Requests\BackOffice\Register\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Services\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage products', only: ['create', 'store', 'edit', 'update', 'destroy', 'preview', 'applyPreview', 'apply', 'syncFromCatalog', 'duplicate']),
        ];
    }

    public function index(PriceListService $service): Response
    {
        $priceLists = PriceList::query()
            ->withCount('items')
            ->orderBy('name')
            ->get()
            ->map(fn (PriceList $priceList): array => [
                'id' => $priceList->id,
                'name' => $priceList->name,
                'notes' => $priceList->notes,
                'is_active' => $priceList->is_active,
                'last_applied_at' => $priceList->last_applied_at,
                'items_count' => $priceList->getAttribute('items_count'),
                'drift' => $service->drift($priceList),
            ]);

        return Inertia::render('backOffice/Register/PriceLists/Index', [
            'priceLists' => $priceLists,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Register/PriceLists/Create', [
            'products' => Product::query()->with('category')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePriceListRequest $request, PriceListService $service): RedirectResponse
    {
        $service->create($request->validated());

        return redirect()->route('bo.register.price-lists.index')->with('success', 'Liste de prix créée.');
    }

    public function edit(PriceList $priceList): Response
    {
        $priceList->load('items');

        return Inertia::render('backOffice/Register/PriceLists/Edit', [
            'priceList' => $priceList,
            'products' => Product::query()->with('category')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList, PriceListService $service): RedirectResponse
    {
        $service->update($priceList, $request->validated());

        return redirect()->route('bo.register.price-lists.index')->with('success', 'Liste de prix mise à jour.');
    }

    public function destroy(PriceList $priceList, PriceListService $service): RedirectResponse
    {
        $service->delete($priceList);

        return redirect()->route('bo.register.price-lists.index')->with('success', 'Liste de prix supprimée.');
    }

    public function preview(PriceList $priceList, PriceListService $service): JsonResponse
    {
        return response()->json($service->preview($priceList));
    }

    public function applyPreview(PriceList $priceList, PriceListService $service): Response
    {
        return Inertia::render('backOffice/Register/PriceLists/Apply', [
            'priceList' => $priceList,
            'preview' => $service->preview($priceList),
        ]);
    }

    public function apply(PriceList $priceList, PriceListService $service): RedirectResponse
    {
        $result = $service->apply($priceList);

        return back()->with('success', "Prix appliqués : {$result['updated']} produit(s) mis à jour, {$result['skipped']} ignoré(s).");
    }

    public function syncFromCatalog(PriceList $priceList, PriceListService $service): RedirectResponse
    {
        $result = $service->updateFromCatalog($priceList);

        return back()->with('success', "Liste synchronisée : {$result['added']} produit(s) ajouté(s), {$result['removed']} retiré(s).");
    }

    public function duplicate(PriceList $priceList, PriceListService $service): RedirectResponse
    {
        $copy = $service->duplicate($priceList);

        return redirect()->route('bo.register.price-lists.edit', $copy)->with('success', 'Liste de prix dupliquée.');
    }
}
