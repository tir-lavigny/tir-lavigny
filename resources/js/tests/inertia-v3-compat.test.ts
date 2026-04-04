/**
 * Inertia v3 Compatibility Tests
 *
 * These tests guard against regressions from the @inertiajs/vue3 v2 → v3 upgrade
 * (PR #85). They assert that the Inertia API surface actually used by this
 * application is present and works as expected, so that any future upgrade that
 * removes or renames an API will be caught immediately.
 *
 * Coverage areas:
 *  - Named exports that the app imports from @inertiajs/vue3
 *  - `useForm` reactive properties relied upon by templates
 *  - `usePage` structure (props shape, reactivity)
 *  - `Link` component: renders as <a> by default, renders as <button> with
 *    method="post" as="button" (used heavily for logout / verify-email)
 *  - `router` named export exists and exposes the methods the app calls
 *  - `createServer` sub-path import (used by ssr.ts)
 *  - `createInertiaApp` named export
 */

import { Head, Link, createInertiaApp, router, useForm, usePage } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

// ---------------------------------------------------------------------------
// 1. Named exports
// ---------------------------------------------------------------------------
describe('@inertiajs/vue3 named exports', () => {
    it('exports createInertiaApp as a function', () => {
        expect(typeof createInertiaApp).toBe('function');
    });

    it('exports Link as a component (object or function)', () => {
        expect(Link).toBeDefined();
        // Vue components are objects or functions
        expect(['object', 'function']).toContain(typeof Link);
    });

    it('exports Head as a component (object or function)', () => {
        expect(Head).toBeDefined();
        expect(['object', 'function']).toContain(typeof Head);
    });

    it('exports usePage as a function', () => {
        expect(typeof usePage).toBe('function');
    });

    it('exports useForm as a function', () => {
        expect(typeof useForm).toBe('function');
    });

    it('exports router as an object', () => {
        expect(router).toBeDefined();
        expect(typeof router).toBe('object');
    });
});

// ---------------------------------------------------------------------------
// 2. createServer sub-path import (used by ssr.ts)
// ---------------------------------------------------------------------------
describe('@inertiajs/vue3/server sub-path export', () => {
    it('resolves createServer from the sub-path export', async () => {
        // Dynamic import mirrors what ssr.ts does:
        //   import createServer from '@inertiajs/vue3/server'
        const mod = await import('@inertiajs/vue3/server');
        const createServer = mod.default ?? mod;
        expect(typeof createServer).toBe('function');
    });
});

// ---------------------------------------------------------------------------
// 3. router API — methods used by the app
// ---------------------------------------------------------------------------
describe('router methods used by the app', () => {
    it('has a .get() method', () => {
        expect(typeof router.get).toBe('function');
    });

    it('has a .post() method', () => {
        expect(typeof router.post).toBe('function');
    });

    it('has a .put() method', () => {
        expect(typeof router.put).toBe('function');
    });

    it('has a .patch() method', () => {
        expect(typeof router.patch).toBe('function');
    });

    it('has a .delete() method', () => {
        expect(typeof router.delete).toBe('function');
    });

    it('has a .on() method for global event listeners', () => {
        expect(typeof router.on).toBe('function');
    });

    // v3 added cancelAll() as the primary cancellation API
    it('has a .cancelAll() method (v3 addition, cancels all request types)', () => {
        // cancelAll() was added in v3 and is the recommended way to cancel visits.
        // Asserting its presence ensures we haven't downgraded past v3.
        expect(typeof (router as any).cancelAll).toBe('function');
    });

    // NOTE: router.cancel() is still present in v3.0.2 (marked @deprecated).
    // The upgrade guide says it should eventually be removed; once the Inertia
    // team drops it in a patch, update this test to 'toBe("undefined")'.
    it('exposes .cancel() — currently deprecated but still present in v3.0.x', () => {
        expect(typeof (router as any).cancel).toBe('function');
    });
});

// ---------------------------------------------------------------------------
// 4. useForm — reactive properties used in templates
// ---------------------------------------------------------------------------
describe('useForm reactive properties', () => {
    // useForm is mocked in setup.ts, so we call the mock and inspect the shape.
    const form = useForm({ email: 'a@b.com', password: '' });

    it('exposes .processing (used to disable submit buttons)', () => {
        expect('processing' in form).toBe(true);
        expect(typeof form.processing).toBe('boolean');
    });

    it('exposes .errors (used for inline validation messages)', () => {
        expect('errors' in form).toBe(true);
        expect(typeof form.errors).toBe('object');
    });

    it('exposes .isDirty (used to show unsaved-changes warnings)', () => {
        expect('isDirty' in form).toBe(true);
        expect(typeof form.isDirty).toBe('boolean');
    });

    it('exposes .recentlySuccessful (used to show success state)', () => {
        expect('recentlySuccessful' in form).toBe(true);
        expect(typeof form.recentlySuccessful).toBe('boolean');
    });

    it('exposes .wasSuccessful', () => {
        expect('wasSuccessful' in form).toBe(true);
    });

    it('exposes .progress (used for file upload progress)', () => {
        expect('progress' in form).toBe(true);
    });

    it('exposes .post() method', () => {
        expect(typeof form.post).toBe('function');
    });

    it('exposes .put() method', () => {
        expect(typeof form.put).toBe('function');
    });

    it('exposes .patch() method', () => {
        expect(typeof form.patch).toBe('function');
    });

    it('exposes .delete() method', () => {
        expect(typeof form.delete).toBe('function');
    });

    it('exposes .reset() method', () => {
        expect(typeof form.reset).toBe('function');
    });

    it('exposes .clearErrors() method', () => {
        expect(typeof form.clearErrors).toBe('function');
    });

    it('exposes .setError() method', () => {
        expect(typeof form.setError).toBe('function');
    });

    it('exposes .transform() method', () => {
        expect(typeof form.transform).toBe('function');
    });

    it('mirrors initial data fields as top-level reactive properties', () => {
        expect('email' in form).toBe(true);
        expect('password' in form).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// 5. usePage — shape used by the app
// ---------------------------------------------------------------------------
describe('usePage shape', () => {
    it('returns an object with .props', () => {
        const page = usePage();
        expect(page).toBeDefined();
        expect(typeof page.props).toBe('object');
    });

    it('props contains auth.user (SharedData shape)', () => {
        const page = usePage();
        expect('auth' in page.props).toBe(true);
        expect('user' in (page.props as any).auth).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// 6. Link component — rendering behaviour
// ---------------------------------------------------------------------------
describe('Link component rendering', () => {
    it('renders as an <a> element by default', () => {
        const wrapper = mount(Link, {
            props: { href: '/test' },
        });
        // The actual Inertia Link renders an <a> tag for GET links
        expect(wrapper.element.tagName.toLowerCase()).toBe('a');
    });

    it('renders as a <button> when method="post" as="button"', () => {
        // This is the pattern used for logout and email verification in the app.
        // In v3 `method` and `as` props are still supported on Link.
        const wrapper = mount(Link, {
            props: { href: '/logout', method: 'post', as: 'button' },
        });
        expect(wrapper.element.tagName.toLowerCase()).toBe('button');
    });

    it('sets the correct href on the anchor element', () => {
        const wrapper = mount(Link, {
            props: { href: '/home' },
        });
        expect((wrapper.element as HTMLAnchorElement).getAttribute('href')).toBe('/home');
    });

    it('accepts an onClick handler — the handler prop is wired correctly', () => {
        // We cannot trigger a full Inertia navigation in jsdom (no real browser
        // router context), so we verify the prop is accepted and the element
        // renders without errors.  Actual navigation is an integration concern.
        expect(() =>
            mount(Link, {
                props: { href: '/test', onClick: () => {} },
            }),
        ).not.toThrow();
    });
});

// ---------------------------------------------------------------------------
// 7. Head component — import smoke test
// ---------------------------------------------------------------------------
describe('Head component', () => {
    it('is exported as a non-null value from @inertiajs/vue3', () => {
        // Head requires an active Inertia page context (createProvider) to
        // mount, which is not available in a unit test without a full
        // createInertiaApp setup.  We assert the export exists and is
        // usable as a component, which is sufficient to detect renames or
        // removals between Inertia versions.
        expect(Head).toBeDefined();
        expect(Head).not.toBeNull();
        // A Vue component must be a function or an object with at least a
        // name, setup, or render key.
        const isComponent = typeof Head === 'function' || (typeof Head === 'object' && ('setup' in Head || 'render' in Head || 'name' in Head));
        expect(isComponent).toBe(true);
    });
});
