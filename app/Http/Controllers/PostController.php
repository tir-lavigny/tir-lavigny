<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\MarkdownText;
use App\Support\Seo;
use Inertia\Response;

class PostController extends Controller
{
    public function show(string $public_id): Response
    {
        $post = Post::published()->where('public_id', $public_id)->firstOrFail();
        $description = MarkdownText::toPlainText($post->content);

        return $this->renderInertia('Post', [
            'post' => $post,
            'og' => [
                'title' => $post->title,
                'description' => $description,
                'image' => asset('/images/hero.webp'),
                'type' => 'article',
            ],
            'json_ld' => [
                Seo::post($post, $description),
            ],
            'breadcrumbs' => [
                ['title' => 'Accueil', 'href' => route('home')],
                ['title' => $post->title, 'href' => route('posts.show', $post->public_id)],
            ],
        ]);
    }
}
