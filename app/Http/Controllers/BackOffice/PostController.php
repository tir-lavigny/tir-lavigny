<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class PostController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:create post', only: ['create', 'store']),
            new Middleware('permission:edit post', only: ['edit', 'update']),
            new Middleware('permission:delete post', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', null);

        $query = Post::query();

        if ($search) {
            $search = strtolower($search);
            $query->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('publish_at', 'desc');

        $data = $query->paginate(5)->withQueryString();

        return Inertia::render('backOffice/Posts/Index', [
            'paginatedPosts' => $data,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('backOffice/Posts/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'publish_at' => 'required|date',
            'expire_at' => 'nullable|date|after:publish_at',
        ]);

        $post = new Post;
        $post->title = $request->input('title');
        $post->content = $request->input('content');
        $post->publish_at = $request->input('publish_at');
        $post->expire_at = $request->input('expire_at');
        $post->save();

        return redirect()->route('bo.posts.show', $post->public_id)->with('success', 'Post created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $public_id)
    {
        $post = Post::where('public_id', $public_id)->firstOrFail();

        return Inertia::render('backOffice/Posts/Show', [
            'post' => $post,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $public_id)
    {
        $post = Post::where('public_id', $public_id)->firstOrFail();

        return Inertia::render('backOffice/Posts/Edit', [
            'post' => $post,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $public_id)
    {
        $post = Post::where('public_id', $public_id)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'publish_at' => 'required|date',
            'expire_at' => 'nullable|date|after:publish_at',
        ]);

        $post->title = $request->input('title');
        $post->content = $request->input('content');
        $post->publish_at = $request->input('publish_at');
        $post->expire_at = $request->input('expire_at');

        try {
            $post->save();
        } catch (Exception $e) {
            return redirect()->back()->withErrors('Unable to update post. Please try again later.');
        }

        return redirect()->route('bo.posts.show', $post->public_id)->with('success', 'Post updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $public_id)
    {
        $post = Post::where('public_id', $public_id)->firstOrFail();

        try {
            $post->delete();
        } catch (Exception $e) {
            return redirect()->back()->withErrors('Unable to delete post. Please try again later.');
        }

        return redirect()->route('bo.posts.index')->with('success', 'Post deleted successfully.');
    }
}
