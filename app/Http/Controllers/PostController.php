<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;

class PostController extends Controller
{
    public function show(string $public_id)
    {
        $post = Post::where('public_id', $public_id)
            ->where('publish_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expire_at')->orWhere('expire_at', '>', now());
            })
            ->firstOrFail();

        return Inertia::render('Post', [
            'post' => $post,
        ]);
    }
}
