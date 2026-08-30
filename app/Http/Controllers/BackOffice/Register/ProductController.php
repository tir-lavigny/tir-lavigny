<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\StoreProductRequest;
use App\Http\Requests\BackOffice\Register\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage products', only: ['create', 'store', 'edit', 'update', 'destroy', 'attachChild', 'detachChild']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = Product::query()->with('category');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('backOffice/Register/Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search ?? null],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Register/Products/Create', [
            'categories' => Category::whereNull('deleted_at')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        Product::create($request->validated());

        return redirect()->route('bo.register.products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product): Response
    {
        $product->load(['category', 'childProducts']);

        return Inertia::render('backOffice/Register/Products/Show', [
            'product' => $product,
        ]);
    }

    public function edit(Product $product): Response
    {
        $product->load('category');

        return Inertia::render('backOffice/Register/Products/Edit', [
            'product' => $product,
            'categories' => Category::whereNull('deleted_at')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return redirect()->route('bo.register.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('bo.register.products.index')->with('success', 'Product deleted successfully.');
    }

    public function attachChild(Request $request, Product $product)
    {
        $request->validate([
            'child_product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product->childProducts()->attach($request->input('child_product_id'), [
            'quantity' => $request->input('quantity'),
        ]);

        return redirect()->route('bo.register.products.show', $product)->with('success', 'Child product attached.');
    }

    public function detachChild(Product $product, Product $child)
    {
        $product->childProducts()->detach($child->id);

        return redirect()->route('bo.register.products.show', $product)->with('success', 'Child product detached.');
    }
}
