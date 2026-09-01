<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Support\Str;
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
            'og' => [
                'title' => $post->title,
                'description' => $this->cleanOgDescription($post->content),
                'image' => asset('/images/hero.jpg'),
                'type' => 'article',
            ],
        ]);
    }

    private function cleanOgDescription(?string $html): string
    {
        $text = strip_tags($html ?? '');
        $text = preg_replace('/#{1,6}\s/', '', $text);
        $text = preg_replace('/\*\*|__/', '', $text);
        $text = preg_replace('/\*|_/', '', $text);
        $text = preg_replace('/\[(.+?)\]\(.+?\)/', '$1', $text);
        $text = preg_replace('/!\[.*?\]\(.+?\)/', '', $text);
        $text = preg_replace('/`{1,3}[^`]*`{1,3}/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return Str::limit(trim($text), 160, '...');
    }
}
