<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Product::query()
            ->with(['category', 'childProducts']);

        if (! request()->user()->can('manage products')) {
            $query->whereHas('category', function ($q) {
                $q->where('is_restricted', false);
            });
        }

        $products = $query->orderBy('name')->get();

        return response()->json(['data' => ProductResource::collection($products)]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['childProducts', 'category']);

        return response()->json(['data' => new ProductResource($product)]);
    }
}
