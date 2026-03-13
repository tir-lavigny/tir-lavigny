import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AppearanceTabs from './AppearanceTabs.vue';
import { useAppearance } from '@/composables/useAppearance';

// Mock useAppearance
vi.mock('@/composables/useAppearance', () => ({
    useAppearance: vi.fn(),
}));

describe('AppearanceTabs.vue', () => {
    it('renders all three tabs', () => {
        (useAppearance as any).mockReturnValue({
            appearance: { value: 'system' },
            updateAppearance: vi.fn(),
        });

        const wrapper = mount(AppearanceTabs);
        const buttons = wrapper.findAll('button');
        
        expect(buttons).toHaveLength(3);
        expect(wrapper.text()).toContain('Light');
        expect(wrapper.text()).toContain('Dark');
        expect(wrapper.text()).toContain('System');
    });

    it('calls updateAppearance when a tab is clicked', async () => {
        const updateAppearance = vi.fn();
        (useAppearance as any).mockReturnValue({
            appearance: { value: 'system' },
            updateAppearance,
        });

        const wrapper = mount(AppearanceTabs);
        const lightButton = wrapper.findAll('button').find(b => b.text().includes('Light'));
        
        await lightButton?.trigger('click');
        
        expect(updateAppearance).toHaveBeenCalledWith('light');
    });

    it('applies active styles to the current appearance', () => {
        (useAppearance as any).mockReturnValue({
            appearance: 'dark', // ref value is unwrapped in template
            updateAppearance: vi.fn(),
        });

        const wrapper = mount(AppearanceTabs);
        const darkButton = wrapper.findAll('button').find(b => b.text().includes('Dark'));
        
        // Check for active classes (shadow-xs or similar from the component)
        expect(darkButton?.classes()).toContain('bg-white');
    });
});
