import { Button } from '@/components/ui/button';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppPagination from './AppPagination.vue';

describe('AppPagination.vue', () => {
    it('renders correct number of pages', () => {
        const wrapper = mount(AppPagination, {
            props: {
                totalItems: 50,
                itemsPerPage: 10,
            },
        });

        // Should have buttons for 1, 2, 3, 4, 5
        const buttons = wrapper.findAllComponents(Button).filter((b) => !isNaN(Number(b.text())));
        expect(buttons).toHaveLength(5);
    });

    it('emits page-changed when a page is clicked', async () => {
        const wrapper = mount(AppPagination, {
            props: {
                totalItems: 50,
                itemsPerPage: 10,
            },
        });

        const page2Button = wrapper.findAllComponents(Button).find((b) => b.text() === '2');
        await page2Button?.trigger('click');

        expect(wrapper.emitted('page-changed')).toBeTruthy();
        expect(wrapper.emitted('page-changed')?.[0]).toEqual([2]);
    });

    it('hides first/last buttons when showFirstLast is false', () => {
        const wrapper = mount(AppPagination, {
            props: {
                totalItems: 50,
                itemsPerPage: 10,
                showFirstLast: false,
            },
        });

        // PaginationFirst and PaginationLast are functional components or components that we can check by their existence
        // In shadcn-vue, these often render as specific components
        expect(wrapper.findComponent({ name: 'PaginationFirst' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'PaginationLast' }).exists()).toBe(false);
    });

    it('hides prev/next buttons when showPrevNext is false', () => {
        const wrapper = mount(AppPagination, {
            props: {
                totalItems: 50,
                itemsPerPage: 10,
                showPrevNext: false,
            },
        });

        expect(wrapper.findComponent({ name: 'PaginationPrevious' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'PaginationNext' }).exists()).toBe(false);
    });

    it('renders ellipsis when there are many pages', () => {
        const wrapper = mount(AppPagination, {
            props: {
                totalItems: 200, // 20 pages
                itemsPerPage: 10,
                siblingCount: 1,
            },
        });

        expect(wrapper.findComponent({ name: 'PaginationEllipsis' }).exists()).toBe(true);
    });
});
