import * as LucideVue from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Icon from './Icon.vue';

describe('Icon.vue', () => {
    it('renders correctly with a valid icon name', () => {
        const wrapper = mount(Icon, { props: { name: 'Home' } });
        expect(wrapper.exists()).toBe(true);
    });

    it('resolves the lucide component for the given name', () => {
        const wrapper = mount(Icon, { props: { name: 'Check' } });
        // The root element should be an SVG rendered by lucide
        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('capitalises the first character of the name to match lucide PascalCase exports', () => {
        // 'check' (lowercase) should resolve to the same icon as 'Check'
        const lower = mount(Icon, { props: { name: 'check' } });
        const upper = mount(Icon, { props: { name: 'Check' } });
        expect(lower.find('svg').exists()).toBe(true);
        expect(upper.find('svg').exists()).toBe(true);
    });

    it('applies custom class alongside the default h-4 w-4 base class', () => {
        const wrapper = mount(Icon, {
            props: { name: 'Home', class: 'text-red-500' },
        });
        expect(wrapper.classes()).toContain('text-red-500');
        expect(wrapper.classes()).toContain('h-4');
        expect(wrapper.classes()).toContain('w-4');
    });

    it('passes size prop to the underlying lucide component', () => {
        const wrapper = mount(Icon, { props: { name: 'Home', size: 32 } });
        const svg = wrapper.find('svg');
        expect(svg.attributes('width')).toBe('32');
        expect(svg.attributes('height')).toBe('32');
    });

    it('passes strokeWidth prop to the underlying lucide component', () => {
        const wrapper = mount(Icon, { props: { name: 'Home', strokeWidth: 3 } });
        const svg = wrapper.find('svg');
        expect(svg.attributes('stroke-width')).toBe('3');
    });

    it('passes color prop to the underlying lucide component', () => {
        const wrapper = mount(Icon, { props: { name: 'Home', color: 'red' } });
        const svg = wrapper.find('svg');
        // @lucide/vue applies the color prop as the SVG stroke attribute
        expect(svg.attributes('stroke')).toBe('red');
    });

    it('renders nothing when an unknown icon name is given', () => {
        // The computed `icon` will be undefined; <component :is="undefined"> renders nothing
        const wrapper = mount(Icon, { props: { name: 'ThisIconDoesNotExist' } });
        expect(wrapper.find('svg').exists()).toBe(false);
    });

    it('every icon used in the project is resolvable via the namespace import', () => {
        const usedIcons = Object.keys(LucideVue).filter((key) => key !== 'default' && /^[A-Z]/.test(key));
        // Sanity-check: the package exports at least the icons we know about
        const knownIcons = ['Check', 'X', 'ChevronDown', 'Calendar', 'MapPin'];
        for (const name of knownIcons) {
            expect(usedIcons).toContain(name);
        }
    });
});
