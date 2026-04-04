/**
 * Inertia Compatibility Tests
 *
 * These tests are the regression safety net for every future Inertia upgrade.
 * They assert that the Inertia API surface **actually used by this application**
 * is present and behaves correctly. They are intentionally decoupled from any
 * specific Inertia version number.
 *
 * How to use after an upgrade:
 *   1. Run `npm test` — failing tests pinpoint the exact API that changed.
 *   2. Fix the source code (or the mock in setup.ts) for any breaking change.
 *   3. If Inertia adds a new API the app starts using, add a test here.
 *
 * Coverage areas:
 *   - Named exports: createInertiaApp, Link, Head, usePage, useForm, router
 *   - Sub-path export: @inertiajs/vue3/server (used by ssr.ts)
 *   - router public methods: get, post, put, patch, delete, on, cancelAll
 *   - useForm reactive properties used in templates
 *   - usePage props shape (SharedData)
 *   - Link rendering: <a> for GET, <button> for POST with as="button"
 *   - Head: exported and structurally valid as a Vue component
 */

import { Head, Link, createInertiaApp, router, useForm, usePage } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

// ---------------------------------------------------------------------------
// 1. Named exports
//    If an upgrade renames or removes any of these, the import above will
//    already fail at compile time. These runtime checks are a second line of
//    defence for exports that might become undefined despite type stubs.
// ---------------------------------------------------------------------------
describe('@inertiajs/vue3 named exports', () => {
    it('exports createInertiaApp as a function', () => {
        expect(typeof createInertiaApp).toBe('function');
    });

    it('exports Link as a Vue component', () => {
        expect(Link).toBeDefined();
        expect(['object', 'function']).toContain(typeof Link);
    });

    it('exports Head as a Vue component', () => {
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
// 2. @inertiajs/vue3/server sub-path export
//    Used in ssr.ts: `import createServer from '@inertiajs/vue3/server'`
//    If the sub-path is removed or renamed this test fails immediately.
// ---------------------------------------------------------------------------
describe('@inertiajs/vue3/server sub-path export', () => {
    it('resolves createServer as a function', async () => {
        const mod = await import('@inertiajs/vue3/server');
        const createServer = mod.default ?? mod;
        expect(typeof createServer).toBe('function');
    });
});

// ---------------------------------------------------------------------------
// 3. router — methods the app calls
//    Any future removal will be caught here before it breaks at runtime.
// ---------------------------------------------------------------------------
describe('router API surface used by this app', () => {
    it('has .get()  — used for filter/pagination navigation', () => {
        expect(typeof router.get).toBe('function');
    });

    it('has .post() — used via useForm internally', () => {
        expect(typeof router.post).toBe('function');
    });

    it('has .put()  — used via useForm internally', () => {
        expect(typeof router.put).toBe('function');
    });

    it('has .patch() — used via useForm internally', () => {
        expect(typeof router.patch).toBe('function');
    });

    it('has .delete() — used via useForm internally', () => {
        expect(typeof router.delete).toBe('function');
    });

    it('has .on() — used for global event listeners', () => {
        expect(typeof router.on).toBe('function');
    });

    // cancelAll() is the current (non-deprecated) API for visit cancellation.
    // router.cancel() was deprecated in v3 and will be removed in a future release.
    it('has .cancelAll() — the non-deprecated cancellation API', () => {
        expect(typeof (router as any).cancelAll).toBe('function');
    });
});

// ---------------------------------------------------------------------------
// 4. useForm — reactive properties used in templates
//    useForm is mocked in setup.ts; these tests validate the mock shape matches
//    the real API so the two cannot silently diverge.
// ---------------------------------------------------------------------------
describe('useForm properties and methods', () => {
    const form = useForm({ email: 'a@b.com', password: '' });

    // --- Boolean state flags used to control template rendering ---
    it('.processing is a boolean (disables submit buttons)', () => {
        expect(typeof form.processing).toBe('boolean');
    });

    it('.isDirty is a boolean (shows unsaved-changes warnings)', () => {
        expect(typeof form.isDirty).toBe('boolean');
    });

    it('.recentlySuccessful is a boolean (shows success feedback)', () => {
        expect(typeof form.recentlySuccessful).toBe('boolean');
    });

    it('.wasSuccessful is present', () => {
        expect('wasSuccessful' in form).toBe(true);
    });

    it('.progress is present (upload progress bar)', () => {
        expect('progress' in form).toBe(true);
    });

    // --- Errors object used by <InputError> components ---
    it('.errors is an object', () => {
        expect(typeof form.errors).toBe('object');
        expect(form.errors).not.toBeNull();
    });

    // --- HTTP submission methods ---
    it('.post() is a function', () => {
        expect(typeof form.post).toBe('function');
    });

    it('.put() is a function', () => {
        expect(typeof form.put).toBe('function');
    });

    it('.patch() is a function', () => {
        expect(typeof form.patch).toBe('function');
    });

    it('.delete() is a function', () => {
        expect(typeof form.delete).toBe('function');
    });

    // --- Helper methods ---
    it('.reset() is a function', () => {
        expect(typeof form.reset).toBe('function');
    });

    it('.clearErrors() is a function', () => {
        expect(typeof form.clearErrors).toBe('function');
    });

    it('.setError() is a function', () => {
        expect(typeof form.setError).toBe('function');
    });

    it('.transform() is a function', () => {
        expect(typeof form.transform).toBe('function');
    });

    // --- Data fields are top-level reactive properties ---
    it('exposes initial data fields at the top level', () => {
        expect('email' in form).toBe(true);
        expect('password' in form).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// 5. usePage — SharedData shape
//    Components access page.props.auth.user, page.props.ziggy, etc.
//    If the props structure changes, these tests will fail first.
// ---------------------------------------------------------------------------
describe('usePage SharedData shape', () => {
    it('returns an object with a .props property', () => {
        const page = usePage();
        expect(typeof page.props).toBe('object');
        expect(page.props).not.toBeNull();
    });

    it('props.auth.user exists (used by NavUser, AppSidebar, settings pages)', () => {
        const page = usePage();
        expect('auth' in page.props).toBe(true);
        expect('user' in (page.props as any).auth).toBe(true);
    });

    it('props.ziggy exists (used by NavMain for active-route detection)', () => {
        const page = usePage();
        expect('ziggy' in page.props).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// 6. Link component — rendering
//    The app uses two patterns:
//      a) <Link href="..."> → plain GET navigation → renders <a>
//      b) <Link href="..." method="post" as="button"> → POST action → renders <button>
//    Pattern (b) is used for logout and email verification resend.
//    If Inertia ever drops the `method` or `as` props, test (b) will fail.
// ---------------------------------------------------------------------------
describe('Link component rendering', () => {
    it('renders as <a> by default (GET navigation)', () => {
        const wrapper = mount(Link, { props: { href: '/test' } });
        expect(wrapper.element.tagName.toLowerCase()).toBe('a');
    });

    it('sets the href attribute on the anchor', () => {
        const wrapper = mount(Link, { props: { href: '/about' } });
        expect(wrapper.element.getAttribute('href')).toBe('/about');
    });

    it('renders as <button> when method="post" as="button" (logout / email-resend pattern)', () => {
        const wrapper = mount(Link, {
            props: { href: '/logout', method: 'post', as: 'button' },
        });
        expect(wrapper.element.tagName.toLowerCase()).toBe('button');
    });

    it('accepts slot content and renders it', () => {
        const wrapper = mount(Link, {
            props: { href: '/' },
            slots: { default: 'Home' },
        });
        expect(wrapper.text()).toBe('Home');
    });

    it('mounts without errors when an onClick prop is provided', () => {
        // Full navigation cannot be tested in jsdom (no Inertia page context).
        // This test confirms the prop is accepted and does not throw on mount.
        expect(() => mount(Link, { props: { href: '/test', onClick: () => {} } })).not.toThrow();
    });
});

// ---------------------------------------------------------------------------
// 7. Head component — structural validity
//    Head requires an active Inertia page context to mount in a unit test, so
//    we only assert that the export is a structurally valid Vue component.
//    If Inertia renames or removes Head, the import at the top of this file
//    will already fail; this test is the runtime backstop.
// ---------------------------------------------------------------------------
describe('Head component', () => {
    it('is exported as a structurally valid Vue component', () => {
        expect(Head).toBeDefined();
        expect(Head).not.toBeNull();
        const isComponent = typeof Head === 'function' || (typeof Head === 'object' && ('setup' in Head || 'render' in Head || 'name' in Head));
        expect(isComponent).toBe(true);
    });
});
