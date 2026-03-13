import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { initializeTheme, updateTheme, useAppearance } from './useAppearance';

describe('useAppearance.ts', () => {
    // Helper to mock matchMedia
    const mockMatchMedia = (matches: boolean) => {
        return vi.fn().mockImplementation((query) => ({
            matches,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        }));
    };

    beforeEach(() => {
        // Clear mocks and reset DOM
        vi.clearAllMocks();
        localStorage.clear();
        document.documentElement.classList.remove('dark');
        document.cookie = '';

        // Mock window.matchMedia
        window.matchMedia = mockMatchMedia(false);
    });

    describe('SSR environment (undefined window/document)', () => {
        afterEach(() => {
            vi.unstubAllGlobals();
        });

        it('should handle undefined window/document gracefully', () => {
            vi.stubGlobal('window', undefined);
            vi.stubGlobal('document', undefined);

            // These should not throw
            updateTheme('dark');
            initializeTheme();

            // Testing useAppearance in SSR
            const { updateAppearance } = useAppearance();
            updateAppearance('dark');
        });
    });

    describe('System theme changes', () => {
        it('should react to system theme changes', () => {
            let changeHandler: any;
            window.matchMedia = vi.fn().mockImplementation(() => ({
                matches: false,
                addEventListener: (event: string, handler: any) => {
                    if (event === 'change') changeHandler = handler;
                },
            }));

            initializeTheme();

            // Simulate system theme change to dark
            window.matchMedia = mockMatchMedia(true);
            changeHandler();
            expect(document.documentElement.classList.contains('dark')).toBe(true);

            // Simulate system theme change to light
            window.matchMedia = mockMatchMedia(false);
            changeHandler();
            expect(document.documentElement.classList.contains('dark')).toBe(false);
        });
    });

    describe('initializeTheme', () => {
        it('should initialize theme from localStorage', () => {
            localStorage.setItem('appearance', 'dark');
            initializeTheme();
            expect(document.documentElement.classList.contains('dark')).toBe(true);
        });

        it('should initialize theme from system if nothing in localStorage', () => {
            window.matchMedia = mockMatchMedia(true);
            initializeTheme();
            expect(document.documentElement.classList.contains('dark')).toBe(true);
        });

        it('should add event listener for system theme changes', () => {
            const addEventListener = vi.fn();
            window.matchMedia = vi.fn().mockImplementation(() => ({
                matches: false,
                addEventListener,
            }));

            initializeTheme();
            expect(addEventListener).toHaveBeenCalledWith('change', expect.any(Function));
        });
    });

    describe('useAppearance composable', () => {
        // We need a component to test onMounted
        const TestComponent = defineComponent({
            setup() {
                return useAppearance();
            },
            render() {
                return h('div');
            },
        });

        it('should load initial appearance from localStorage on mount', async () => {
            localStorage.setItem('appearance', 'dark');
            const wrapper = mount(TestComponent);

            expect(wrapper.vm.appearance).toBe('dark');
        });

        it('should update appearance, localStorage, and cookie', async () => {
            const wrapper = mount(TestComponent);

            wrapper.vm.updateAppearance('dark');

            expect(wrapper.vm.appearance).toBe('dark');
            expect(localStorage.getItem('appearance')).toBe('dark');
            expect(document.cookie).toContain('appearance=dark');
            expect(document.documentElement.classList.contains('dark')).toBe(true);
        });

        it('should default to "system" if nothing in localStorage', () => {
            const wrapper = mount(TestComponent);
            expect(wrapper.vm.appearance).toBe('system');
        });
    });
});
