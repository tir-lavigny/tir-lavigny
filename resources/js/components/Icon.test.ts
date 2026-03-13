import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Icon from './Icon.vue';

describe('Icon.vue', () => {
    it('renders correctly with given name', () => {
        const wrapper = mount(Icon, {
            props: {
                name: 'home',
            },
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('applies custom class', () => {
        const wrapper = mount(Icon, {
            props: {
                name: 'home',
                class: 'text-red-500',
            },
        });
        expect(wrapper.classes()).toContain('text-red-500');
    });
});
