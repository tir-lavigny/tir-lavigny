import { createPermission, createRole, test } from '@/tests';
import { describe, expect, vi } from 'vitest';
import { ref } from 'vue';
import * as Utils from './utils';

describe('utils.ts', () => {
    describe('cn', () => {
        test('merges tailwind classes correctly', () => {
            expect(Utils.cn('p-4', 'bg-red-500')).toBe('p-4 bg-red-500');
            expect(Utils.cn('p-4 p-8', 'bg-red-500')).toBe('p-8 bg-red-500');
        });

        test('handles conditional classes', () => {
            expect(Utils.cn('p-4', false && 'bg-red-500', 'text-white')).toBe('p-4 text-white');
            expect(Utils.cn('p-4', true && 'bg-red-500')).toBe('p-4 bg-red-500');
        });

        test('handles empty/undefined inputs', () => {
            expect(Utils.cn()).toBe('');
            expect(Utils.cn('', undefined, null)).toBe('');
        });
    });

    describe('formatDate', () => {
        test('formats date correctly with default locale', () => {
            const date = '2025-07-03T19:35:00';
            expect(Utils.formatDate(date, 'DD/MM/YYYY')).toBe('03/07/2025');
        });

        test('handles dates with Z suffix', () => {
            const date = '2025-07-03T19:35:00Z';
            expect(Utils.formatDate(date, 'DD/MM/YYYY')).toBe('03/07/2025');
        });

        test('overrides options like locale', () => {
            const date = '2025-07-03T19:35:00';
            // UseDateFormatOptions doesn't directly expose locale in a way we can easily test here without deep mock,
            // but we can test if other options are passed through if needed.
            expect(Utils.formatDate(date, 'MMMM', { locales: 'en-US' })).toBe('July');
        });

        test('handles invalid date strings', () => {
            const result = Utils.formatDate('invalid-date', 'DD/MM/YYYY');
            expect(result).toBe('NaN/NaN/NaN');
        });
    });

    describe('valueUpdater', () => {
        test('updates ref with a direct value', () => {
            const count = ref(0);
            Utils.valueUpdater(10, count);
            expect(count.value).toBe(10);
        });

        test('updates ref with a function', () => {
            const count = ref(10);
            Utils.valueUpdater((prev: number) => prev + 5, count);
            expect(count.value).toBe(15);
        });

        test('updates ref with an object', () => {
            const state = ref({ a: 1 });
            Utils.valueUpdater({ a: 2 }, state);
            expect(state.value).toEqual({ a: 2 });
        });
    });

    describe('handleTabKey', () => {
        let textarea: HTMLTextAreaElement;

        beforeEach(() => {
            textarea = document.createElement('textarea');
            document.body.appendChild(textarea);
        });

        afterEach(() => {
            document.body.removeChild(textarea);
        });

        test('inserts a tab and moves cursor', () => {
            textarea.value = 'Hello world';
            textarea.selectionStart = 5;
            textarea.selectionEnd = 5;

            const event = new KeyboardEvent('keydown', { key: 'Tab' });
            vi.spyOn(event, 'preventDefault');
            Object.defineProperty(event, 'target', { value: textarea });

            Utils.handleTabKey(event);

            expect(textarea.value).toBe('Hello\t world');
            expect(textarea.selectionStart).toBe(6);
            expect(textarea.selectionEnd).toBe(6);
            expect(event.preventDefault).toHaveBeenCalled();
        });

        test('replaces selection with a tab', () => {
            textarea.value = 'Hello world';
            textarea.selectionStart = 0;
            textarea.selectionEnd = 5;

            const event = new KeyboardEvent('keydown', { key: 'Tab' });
            Object.defineProperty(event, 'target', { value: textarea });

            Utils.handleTabKey(event);

            expect(textarea.value).toBe('\t world');
            expect(textarea.selectionStart).toBe(1);
        });

        test('does nothing if key is not Tab', () => {
            textarea.value = 'Hello';
            const event = new KeyboardEvent('keydown', { key: 'Enter' });
            vi.spyOn(event, 'preventDefault');
            Object.defineProperty(event, 'target', { value: textarea });

            Utils.handleTabKey(event);

            expect(textarea.value).toBe('Hello');
            expect(event.preventDefault).not.toHaveBeenCalled();
        });
    });

    describe('debounce', () => {
        beforeEach(() => {
            vi.useFakeTimers();
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        test('delays execution and respects wait time', () => {
            const func = vi.fn();
            const debounced = Utils.debounce(func, 100);

            debounced();
            expect(func).not.toHaveBeenCalled();

            vi.advanceTimersByTime(50);
            expect(func).not.toHaveBeenCalled();

            vi.advanceTimersByTime(50);
            expect(func).toHaveBeenCalledTimes(1);
        });

        test('resets timer if called again within wait period', () => {
            const func = vi.fn();
            const debounced = Utils.debounce(func, 100);

            debounced();
            vi.advanceTimersByTime(50);
            debounced(); // Should reset
            vi.advanceTimersByTime(50);
            expect(func).not.toHaveBeenCalled();

            vi.advanceTimersByTime(50);
            expect(func).toHaveBeenCalledTimes(1);
        });

        test('passes arguments correctly', () => {
            const func = vi.fn();
            const debounced = Utils.debounce(func, 100);

            debounced('arg1', 2);
            vi.runAllTimers();
            expect(func).toHaveBeenCalledWith('arg1', 2);
        });

        test('maintains correct this context', () => {
            let context: any;
            const func = function (this: any) {
                context = this;
            };
            const obj = { debounced: Utils.debounce(func, 100) };

            obj.debounced();
            vi.runAllTimers();
            expect(context).toBe(obj);
        });
    });

    describe('hasRole', () => {
        test('returns true when user has the role', ({ user }) => {
            expect(Utils.hasRole(user, 'user')).toBe(true);
        });

        test('returns false when user does not have the role', ({ user }) => {
            expect(Utils.hasRole(user, 'admin')).toBe(false);
        });

        test('returns false when user.roles is undefined', ({ user }) => {
            const userNoRoles = { ...user, roles: undefined };
            expect(Utils.hasRole(userNoRoles as any, 'user')).toBe(false);
        });

        test('returns false when user.roles is an empty array', ({ user }) => {
            const userEmptyRoles = { ...user, roles: [] };
            expect(Utils.hasRole(userEmptyRoles, 'user')).toBe(false);
        });
    });

    describe('hasPermissionTo', () => {
        test('returns true when user has the permission in a role', ({ admin }) => {
            const permission = createPermission({ name: 'edit' });
            admin.roles = [createRole({ name: 'admin', permissions: [permission] })];
            expect(Utils.hasPermissionTo(admin, 'edit')).toBe(true);
        });

        test('returns false when user does not have the permission', ({ admin }) => {
            const permission = createPermission({ name: 'edit' });
            admin.roles = [createRole({ name: 'admin', permissions: [permission] })];
            expect(Utils.hasPermissionTo(admin, 'delete')).toBe(false);
        });

        test('checks all roles for the permission', ({ admin }) => {
            const perm1 = createPermission({ name: 'view' });
            const perm2 = createPermission({ name: 'edit' });
            admin.roles = [createRole({ name: 'r1', permissions: [perm1] }), createRole({ name: 'r2', permissions: [perm2] })];

            expect(Utils.hasPermissionTo(admin, 'view')).toBe(true);
            expect(Utils.hasPermissionTo(admin, 'edit')).toBe(true);
            expect(Utils.hasPermissionTo(admin, 'delete')).toBe(false);
        });

        test('returns false if user.roles is missing', ({ admin }) => {
            const userNoRoles = { ...admin, roles: undefined };
            expect(Utils.hasPermissionTo(userNoRoles as any, 'edit')).toBe(false);
        });

        test('returns false if roles have no permissions property', ({ admin }) => {
            admin.roles = [createRole({ name: 'admin', permissions: undefined })];
            expect(Utils.hasPermissionTo(admin, 'edit')).toBe(false);
        });
    });
});
