import { mount } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import AppEventCalendar from './AppEventCalendar.vue';
import { today, getLocalTimeZone } from '@internationalized/date';
import { toast } from 'vue-sonner';

// Mock internationalized date
vi.mock('@internationalized/date', () => ({
    today: vi.fn().mockReturnValue({ toString: () => '2025-01-01' }),
    getLocalTimeZone: vi.fn().mockReturnValue('UTC'),
}));

// Mock vue-sonner
vi.mock('vue-sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}));

describe('AppEventCalendar.vue', () => {
    const groupedEvents = {
        '2025-01-01': [
            { id: 1, title: 'Event 1', start_date: '2025-01-01T10:00:00', end_date: '2025-01-01T12:00:00' }
        ]
    };

    beforeEach(() => {
        vi.clearAllMocks();
        // Mock scrollIntoView
        window.HTMLElement.prototype.scrollIntoView = vi.fn();
    });

    it('renders empty message when no events', () => {
        const wrapper = mount(AppEventCalendar, {
            props: {
                groupedEvents: {},
                hasEvents: false,
            },
            global: {
                stubs: {
                    Calendar: { template: '<div />' },
                }
            }
        });
        
        expect(wrapper.text()).toContain("Aucun événement pour l'instant.");
    });

    it('renders events when groupedEvents is provided', () => {
        const wrapper = mount(AppEventCalendar, {
            props: {
                groupedEvents,
                hasEvents: true,
            },
            global: {
                stubs: {
                    Calendar: { template: '<div />' },
                    AppEventCalendarCard: true,
                }
            }
        });
        
        expect(wrapper.find('#date-2025-01-01').exists()).toBe(true);
    });

    it('copies link to clipboard and shows toast', async () => {
        const clipboardMock = {
            writeText: vi.fn().mockResolvedValue(undefined),
        };
        Object.defineProperty(navigator, 'clipboard', {
            value: clipboardMock,
            configurable: true,
        });

        const wrapper = mount(AppEventCalendar, {
            props: {
                groupedEvents: {},
                hasEvents: false,
            },
            global: {
                stubs: {
                    Calendar: { template: '<div />' },
                }
            }
        });

        const copyButton = wrapper.findAll('button').find(b => b.text().includes('Google'));
        await copyButton?.trigger('click');

        expect(clipboardMock.writeText).toHaveBeenCalled();
        expect(toast.success).toHaveBeenCalledWith('Lien copié dans le presse-papiers !');
    });
});
