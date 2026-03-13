import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AppEventChainItem from './AppEventChainItem.vue';
import { createUser } from '@/tests/fixtures/user';
import { createRole, createPermission } from '@/tests/fixtures/role';

describe('AppEventChainItem.vue', () => {
    const mockEvent: any = {
        id: 1,
        title: 'Event Title',
        start_datetime: '2025-01-01T10:00:00',
        end_datetime: '2025-01-01T12:00:00',
        location: { name: 'Room 1' },
        pending_conflict_fields: [],
        has_chain_conflict: false,
        overridden_fields: [],
    };

    const adminUser = createUser({
        roles: [createRole({ name: 'admin', permissions: [createPermission({ name: 'edit event chain' })] })]
    });

    const regularUser = createUser({
        roles: [createRole({ name: 'user', permissions: [] })]
    });

    it('renders event details correctly', () => {
        const wrapper = mount(AppEventChainItem, {
            props: { event: mockEvent, user: regularUser },
            global: { 
                stubs: { 
                    Link: true, 
                    AppAlertDialog: true,
                    Tooltip: { template: '<div><slot /></div>' },
                    TooltipTrigger: { template: '<div><slot /></div>' },
                    TooltipContent: { template: '<div><slot /></div>' },
                } 
            }
        });
        
        expect(wrapper.text()).toContain('Event Title');
        expect(wrapper.text()).toContain('Room 1');
    });

    it('shows conflict badge when there are pending conflicts', () => {
        const eventWithConflicts = { ...mockEvent, pending_conflict_fields: ['title'] };
        const wrapper = mount(AppEventChainItem, {
            props: { event: eventWithConflicts, user: adminUser },
            global: { 
                stubs: { 
                    Link: true, 
                    AppAlertDialog: true,
                    Tooltip: { template: '<div><slot /></div>' },
                    TooltipTrigger: { template: '<div><slot /></div>' },
                    TooltipContent: { template: '<div><slot /></div>' },
                } 
            }
        });
        
        expect(wrapper.text()).toContain('1 conflit');
    });

    it('shows "Résoudre" button for admin when there are conflicts', () => {
        const eventWithConflicts = { ...mockEvent, pending_conflict_fields: ['title'] };
        const wrapper = mount(AppEventChainItem, {
            props: { event: eventWithConflicts, user: adminUser },
            global: { 
                stubs: { 
                    Link: true, 
                    AppAlertDialog: true,
                    Tooltip: { template: '<div><slot /></div>' },
                    TooltipTrigger: { template: '<div><slot /></div>' },
                    TooltipContent: { template: '<div><slot /></div>' },
                } 
            }
        });
        
        expect(wrapper.text()).toContain('Résoudre');
    });

    it('emits open-conflict-dialog when Resolve button is clicked', async () => {
        const eventWithConflicts = { ...mockEvent, pending_conflict_fields: ['title'] };
        const wrapper = mount(AppEventChainItem, {
            props: { event: eventWithConflicts, user: adminUser },
            global: { 
                stubs: { 
                    Link: true, 
                    AppAlertDialog: true,
                    Tooltip: { template: '<div><slot /></div>' },
                    TooltipTrigger: { template: '<div><slot /></div>' },
                    TooltipContent: { template: '<div><slot /></div>' },
                } 
            }
        });
        
        const resolveButton = wrapper.findAll('button').find(b => b.text().includes('Résoudre'));
        await resolveButton?.trigger('click');
        
        expect(wrapper.emitted('open-conflict-dialog')).toBeTruthy();
        expect(wrapper.emitted('open-conflict-dialog')?.[0]).toEqual([eventWithConflicts]);
    });
});
