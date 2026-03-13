import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppLogo from './AppLogo.vue';
import AppLogoIcon from './AppLogoIcon.vue';

describe('AppLogo.vue', () => {
    it('renders the logo text correctly', () => {
        const wrapper = mount(AppLogo);
        expect(wrapper.text()).toContain('Société de tir de Lavigny');
    });

    it('renders the logo icon component', () => {
        const wrapper = mount(AppLogo);
        expect(wrapper.findComponent(AppLogoIcon).exists()).toBe(true);
    });
});
