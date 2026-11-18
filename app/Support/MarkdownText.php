<?php

namespace App\Support;

use Illuminate\Support\Str;

class MarkdownText
{
    public static function toPlainText(?string $markdown, int $limit = 160): string
    {
        $text = strip_tags($markdown ?? '');
        $text = preg_replace('/#{1,6}\s/', '', $text);
        $text = preg_replace('/\*\*|__/', '', $text);
        $text = preg_replace('/\*|_/', '', $text);
        $text = preg_replace('/\[(.+?)\]\(.+?\)/', '$1', $text);
        $text = preg_replace('/!\[.*?\]\(.+?\)/', '', $text);
        $text = preg_replace('/`{1,3}[^`]*`{1,3}/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return Str::limit(trim($text), $limit, '...');
    }
}
