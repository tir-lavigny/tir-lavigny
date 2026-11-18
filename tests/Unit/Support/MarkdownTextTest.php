<?php

namespace Tests\Unit\Support;

use App\Support\MarkdownText;
use Tests\TestCase;

class MarkdownTextTest extends TestCase
{
    public function test_removes_markdown_images_from_plain_text(): void
    {
        $this->assertSame(
            'Et voici le texte.',
            MarkdownText::toPlainText('![Cible B](https://example.com/cible.png) Et voici le texte.')
        );
    }

    public function test_keeps_link_labels_and_removes_images(): void
    {
        $this->assertSame(
            'Voir le programme et le lien.',
            MarkdownText::toPlainText('![Illustration](https://example.com/image.jpg) Voir le [programme](https://example.com) et le lien.')
        );
    }

    public function test_limits_plain_text_to_the_requested_length(): void
    {
        $this->assertSame('Lorem ipsum...', MarkdownText::toPlainText('Lorem ipsum dolor sit amet', 11));
    }
}
