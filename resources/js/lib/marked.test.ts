/**
 * Tests for the `marked` library as used across this codebase.
 *
 * The app exclusively calls `marked(str)` (synchronous return or awaited
 * promise) with no custom renderers, tokenizers, or extensions.
 * These tests lock down the HTML output for the Markdown constructs that
 * appear in posts/events/contents so that a future major-version bump will
 * immediately surface any rendering regression.
 */

import { marked } from 'marked';
import { describe, expect, it } from 'vitest';

/**
 * Normalise whitespace so that minor formatting differences in the emitted
 * HTML (trailing newlines, extra spaces) do not cause false failures.
 */
function normalize(html: string): string {
    return html.trim().replace(/\s+/g, ' ');
}

async function render(md: string): Promise<string> {
    return normalize(await marked(md));
}

describe('marked() call signature', () => {
    it('can be called as a plain function and returns a string or Promise', () => {
        const result = marked('hello');
        expect(typeof result === 'string' || result instanceof Promise).toBe(true);
    });

    it('can be awaited (async usage in Post.vue and Posts/Show.vue)', async () => {
        const result = await marked('hello');
        expect(typeof result).toBe('string');
    });

    it('named export { marked } is importable', () => {
        expect(typeof marked).toBe('function');
    });
});

describe('paragraphs', () => {
    it('wraps plain text in <p>', async () => {
        const html = await render('Hello world');
        expect(html).toContain('<p>Hello world</p>');
    });

    it('separates two paragraphs with blank line', async () => {
        const html = await render('First\n\nSecond');
        expect(html).toContain('<p>First</p>');
        expect(html).toContain('<p>Second</p>');
    });
});

describe('headings', () => {
    it('renders h1', async () => {
        const html = await render('# Title');
        expect(html).toMatch(/<h1[^>]*>.*Title.*<\/h1>/);
    });

    it('renders h2', async () => {
        const html = await render('## Section');
        expect(html).toMatch(/<h2[^>]*>.*Section.*<\/h2>/);
    });

    it('renders h3', async () => {
        const html = await render('### Sub');
        expect(html).toMatch(/<h3[^>]*>.*Sub.*<\/h3>/);
    });
});

describe('inline formatting', () => {
    it('renders **bold** as <strong>', async () => {
        const html = await render('**bold**');
        expect(html).toContain('<strong>bold</strong>');
    });

    it('renders *italic* as <em>', async () => {
        const html = await render('*italic*');
        expect(html).toContain('<em>italic</em>');
    });

    it('renders `inline code` as <code>', async () => {
        const html = await render('`code`');
        expect(html).toContain('<code>code</code>');
    });

    it('renders ~~strikethrough~~ as <del>', async () => {
        const html = await render('~~strike~~');
        expect(html).toContain('<del>strike</del>');
    });
});

describe('links', () => {
    it('renders [text](url)', async () => {
        const html = await render('[OpenAI](https://openai.com)');
        expect(html).toContain('<a href="https://openai.com">OpenAI</a>');
    });

    it('renders [text](url "title") with title attribute', async () => {
        const html = await render('[OpenAI](https://openai.com "AI lab")');
        expect(html).toContain('title="AI lab"');
    });
});

describe('images', () => {
    it('renders ![alt](src)', async () => {
        const html = await render('![logo](https://example.com/img.png)');
        expect(html).toContain('<img');
        expect(html).toContain('src="https://example.com/img.png"');
        expect(html).toContain('alt="logo"');
    });
});

describe('tight unordered list', () => {
    const md = '- Item A\n- Item B\n- Item C';

    it('renders a <ul>', async () => {
        const html = await render(md);
        expect(html).toContain('<ul>');
        expect(html).toContain('</ul>');
    });

    it('renders each item as <li>', async () => {
        const html = await render(md);
        expect(html).toContain('<li>Item A</li>');
        expect(html).toContain('<li>Item B</li>');
        expect(html).toContain('<li>Item C</li>');
    });
});

describe('tight ordered list', () => {
    const md = '1. First\n2. Second\n3. Third';

    it('renders an <ol>', async () => {
        const html = await render(md);
        expect(html).toContain('<ol>');
        expect(html).toContain('</ol>');
    });

    it('renders each item as <li>', async () => {
        const html = await render(md);
        expect(html).toContain('<li>First</li>');
        expect(html).toContain('<li>Second</li>');
        expect(html).toContain('<li>Third</li>');
    });
});

describe('loose unordered list (blank lines between items)', () => {
    const md = '- Item A\n\n- Item B\n\n- Item C';

    it('renders a <ul>', async () => {
        const html = await render(md);
        expect(html).toContain('<ul>');
    });

    it('wraps loose list item content in <p>', async () => {
        const html = await render(md);
        expect(html).toContain('<p>Item A</p>');
        expect(html).toContain('<p>Item B</p>');
        expect(html).toContain('<p>Item C</p>');
    });
});

describe('loose ordered list (blank lines between items)', () => {
    const md = '1. First\n\n2. Second\n\n3. Third';

    it('renders an <ol>', async () => {
        const html = await render(md);
        expect(html).toContain('<ol>');
    });

    it('wraps loose list item content in <p>', async () => {
        const html = await render(md);
        expect(html).toContain('<p>First</p>');
        expect(html).toContain('<p>Second</p>');
        expect(html).toContain('<p>Third</p>');
    });
});

describe('task list checkboxes', () => {
    it('renders unchecked checkbox', async () => {
        const html = await render('- [ ] Todo');
        expect(html).toContain('<input');
        expect(html).toContain('type="checkbox"');
        expect(html).toContain('disabled');
        expect(html).not.toContain('checked');
    });

    it('renders checked checkbox', async () => {
        const html = await render('- [x] Done');
        expect(html).toContain('<input');
        expect(html).toContain('type="checkbox"');
        expect(html).toContain('checked');
    });
});

describe('fenced code blocks', () => {
    it('renders a <pre><code> block', async () => {
        const html = await render('```\nconsole.log("hi")\n```');
        expect(html).toContain('<pre>');
        expect(html).toContain('<code>');
    });

    it('preserves language class when specified', async () => {
        const html = await render('```javascript\nconst x = 1\n```');
        expect(html).toMatch(/class="language-javascript"/);
    });
});

describe('blockquotes', () => {
    it('renders > as <blockquote>', async () => {
        const html = await render('> A quote');
        expect(html).toContain('<blockquote>');
        expect(html).toContain('</blockquote>');
    });
});

describe('horizontal rules', () => {
    it('renders --- as <hr>', async () => {
        const html = await render('---');
        expect(html).toContain('<hr');
    });
});

describe('raw HTML passthrough', () => {
    it('passes through inline HTML tags', async () => {
        const html = await render('Hello <strong>world</strong>');
        expect(html).toContain('<strong>world</strong>');
    });
});
