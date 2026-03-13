import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Breadcrumbs from './Breadcrumbs.vue';

describe('Breadcrumbs.vue', () => {
    it('renders breadcrumb items correctly', () => {
        const breadcrumbs = [
            { title: 'Home', href: '/' },
            { title: 'Events', href: '/events' },
            { title: 'Current' },
        ];
        
        const wrapper = mount(Breadcrumbs, {
            props: { breadcrumbs },
            global: {
                stubs: {
                }
            }
        });
        
        expect(wrapper.text()).toContain('Home');
        expect(wrapper.text()).toContain('Events');
        expect(wrapper.text()).toContain('Current');
    });

    it('renders separators between items but not after the last one', () => {
        const breadcrumbs = [
            { title: 'A', href: '/a' },
            { title: 'B' },
        ];
        
        const wrapper = mount(Breadcrumbs, {
            props: { breadcrumbs },
            global: {
                stubs: {
                    Link: true,
                    // Don't stub everything to check rendering logic
                }
            }
        });
        
        // Should find exactly one separator
        expect(wrapper.findAllComponents({ name: 'BreadcrumbSeparator' })).toHaveLength(1);
    });
});
