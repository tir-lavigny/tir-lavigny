/**
 * UserMenuContent.vue tests
 *
 * This component uses Inertia's <Link> with `method="post"` and `as="button"`
 * for the logout action — the most common POST-link pattern in the app.
 * These tests verify the rendered output so a future upgrade that breaks
 * non-GET Link rendering surfaces immediately.
 */

import { createUser } from '@/tests/fixtures/user';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import UserMenuContent from './UserMenuContent.vue';

const user = createUser({ name: 'Alice Dupont', email: 'alice@example.com' });

// Stub heavy UI primitives so the test focuses on link rendering only.
const dropdownStubs = {
    DropdownMenuLabel: { template: '<div><slot /></div>' },
    DropdownMenuSeparator: { template: '<hr />' },
    DropdownMenuGroup: { template: '<div><slot /></div>' },
    DropdownMenuItem: { template: '<div><slot /></div>' },
    UserInfo: { template: '<span>{{ user?.name }}</span>', props: ['user', 'showEmail'] },
};

describe('UserMenuContent.vue', () => {
    it('renders the user name', () => {
        const wrapper = mount(UserMenuContent, {
            props: { user },
            global: { stubs: dropdownStubs },
        });
        expect(wrapper.text()).toContain('Alice Dupont');
    });

    it('renders a settings navigation link', () => {
        const wrapper = mount(UserMenuContent, {
            props: { user },
            global: { stubs: dropdownStubs },
        });
        expect(wrapper.text()).toContain('Réglages');
    });

    it('renders a logout link', () => {
        const wrapper = mount(UserMenuContent, {
            props: { user },
            global: { stubs: dropdownStubs },
        });
        expect(wrapper.text()).toContain('Déconnexion');
    });

    it('renders the logout link as a <button> (method=post as=button)', () => {
        // In v3, Link with method="post" as="button" must still produce a <button>.
        // If Inertia ever removes this prop support, this test fails loudly.
        const wrapper = mount(UserMenuContent, {
            props: { user },
            global: { stubs: dropdownStubs },
        });

        const buttons = wrapper.findAll('button');
        // At least one button should exist — the logout one
        expect(buttons.length).toBeGreaterThan(0);
    });

    it('does not render a plain <form> for the logout action', () => {
        // The app relies on Inertia's Link for logout — not a native form.
        // If someone rewrites this as a form, this test flags the change.
        const wrapper = mount(UserMenuContent, {
            props: { user },
            global: { stubs: dropdownStubs },
        });
        expect(wrapper.find('form').exists()).toBe(false);
    });
});
