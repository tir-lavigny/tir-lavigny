<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->roles()->where('name', 'admin')->exists();
        $categories = Category::where('is_restricted', false)
            ->orWhere(function ($query) use ($isAdmin) {
                if ($isAdmin) {
                    $query->where('is_restricted', true);
                }
            })
            ->orderBy('id')
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }
}
