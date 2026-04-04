/**
 * TextLink.vue tests
 *
 * TextLink is a thin wrapper around Inertia's <Link> component that passes
 * through `href`, `method`, and `as` props. These props are critical for the
 * app's POST-method link pattern (e.g. logout, email verification resend).
 *
 * If Inertia v3+ ever removes or renames these props, these tests will fail
 * and surface the regression before it reaches production.
 */

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TextLink from './TextLink.vue';

describe('TextLink.vue', () => {
    it('renders its default slot content', () => {
        const wrapper = mount(TextLink, {
            props: { href: '/home' },
            slots: { default: 'Home' },
        });
        expect(wrapper.text()).toContain('Home');
    });

    it('renders as an <a> tag for a plain GET link', () => {
        const wrapper = mount(TextLink, {
            props: { href: '/home' },
            slots: { default: 'Home' },
        });
        expect(wrapper.element.tagName.toLowerCase()).toBe('a');
    });

    it('passes href to the underlying Inertia Link', () => {
        const wrapper = mount(TextLink, {
            props: { href: '/about' },
            slots: { default: 'About' },
        });
        expect(wrapper.element.getAttribute('href')).toBe('/about');
    });

    it('renders as a <button> when method="post" and as="button" are passed', () => {
        // This covers the logout / email-resend pattern used across the app:
        //   <TextLink method="post" as="button" :href="route('logout')">
        const wrapper = mount(TextLink, {
            props: { href: '/logout', method: 'post', as: 'button' },
            slots: { default: 'Logout' },
        });
        expect(wrapper.element.tagName.toLowerCase()).toBe('button');
    });

    it('applies the expected styling classes', () => {
        const wrapper = mount(TextLink, {
            props: { href: '/home' },
            slots: { default: 'Home' },
        });
        // Verify at least one characteristic class is present so a UI overhaul
        // that removes the component doesn't silently pass.
        expect(wrapper.element.className).toContain('underline');
    });

    it('passes tabindex through to the rendered element', () => {
        const wrapper = mount(TextLink, {
            props: { href: '/home', tabindex: 3 },
            slots: { default: 'Home' },
        });
        expect(wrapper.element.getAttribute('tabindex')).toBe('3');
    });
});
