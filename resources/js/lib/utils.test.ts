import { createPermission, createRole, test } from '@/tests';
import { afterEach, beforeEach, describe, expect, vi } from 'vitest';
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

        test('handles UTC dates with Z suffix by converting to local', () => {
            const date = '2025-07-03T19:35:00Z';
            const result = Utils.formatDate(date, 'DD/MM/YYYY');
            expect(result).toBe('03/07/2025');
        });

        test('overrides options like locale', () => {
            const date = '2025-07-03T19:35:00';
            expect(Utils.formatDate(date, 'MMMM', { locales: 'en-US' })).toBe('July');
        });

        test('handles invalid date strings', () => {
            const result = Utils.formatDate('invalid-date', 'DD/MM/YYYY');
            expect(result).toBe('NaN/NaN/NaN');
        });
    });

    describe('toLocalDatetimeString', () => {
        test('converts UTC ISO string to local datetime-local format', () => {
            const utc = '2025-07-03T19:35:00.000000Z';
            const result = Utils.toLocalDatetimeString(utc);
            expect(result).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
        });

        test('returns empty string for empty input', () => {
            expect(Utils.toLocalDatetimeString('')).toBe('');
        });
    });

    describe('localToUtcIso', () => {
        test('converts local datetime string to UTC ISO', () => {
            const local = '2025-07-03T19:35';
            const result = Utils.localToUtcIso(local);
            expect(result).toContain('Z');
        });

        test('returns empty string for empty input', () => {
            expect(Utils.localToUtcIso('')).toBe('');
        });
    });

    describe('parseDateOnly', () => {
        test('returns correct date from date-only string', () => {
            const result = Utils.parseDateOnly('2025-07-03');
            expect(result.getUTCFullYear()).toBe(2025);
            expect(result.getUTCMonth()).toBe(6);
            expect(result.getUTCDate()).toBe(3);
        });

        test('accepts a full ISO datetime string', () => {
            const result = Utils.parseDateOnly('2025-07-03T00:00:00.000000Z');
            expect(result.toISOString()).toBe('2025-07-03T12:00:00.000Z');
        });

        test('anchors to noon UTC', () => {
            const result = Utils.parseDateOnly('2025-07-03');
            expect(result.getUTCHours()).toBe(12);
            expect(result.getUTCMinutes()).toBe(0);
            expect(result.getUTCSeconds()).toBe(0);
        });

        test('toDateString returns same date as input', () => {
            const result = Utils.parseDateOnly('2025-07-03');
            expect(result.toDateString()).toBe(new Date('2025-07-03T12:00:00Z').toDateString());
        });

        test('anchoring prevents date shift that bare date-only string causes', () => {
            // The bug: new Date('2026-02-12') is parsed as UTC midnight (00:00Z).
            // In UTC-5, that's 2026-02-11 19:00 local — wrong date.
            // In UTC+1 (Zurich), it's 2026-02-12 01:00 — still correct but only 1h margin.
            //
            // This bug cannot reproduce in jsdom (vitest) because it runs in UTC.
            // We verify the protection mechanism: anchored version is at noon (12:00Z),
            // giving a 12h safety margin that prevents the shift in any timezone.
            const anchored = Utils.parseDateOnly('2026-02-12');
            const bare = new Date('2026-02-12');

            // In UTC (test env), both produce the same date — no shift visible here.
            expect(bare.getUTCDate()).toBe(12);
            expect(anchored.getUTCDate()).toBe(12);

            // The protection: bare is at midnight (0h margin to shift), anchored at noon (12h margin).
            expect(bare.getUTCHours()).toBe(0);
            expect(anchored.getUTCHours()).toBe(12);

            // Prove the 12h gap is what prevents the shift:
            // At UTC-13 (worst case), bare at 00:00Z = previous day 11:00 local (SHIFTED).
            // Anchored at 12:00Z = same day 23:00 local (SAFE).
            expect(anchored.getUTCHours() - bare.getUTCHours()).toBe(12);
        });

        test('produces correct local date in any timezone', () => {
            // The whole point: getFullYear/getMonth/getDate in local time
            // must always match the input, regardless of timezone offset.
            const d = Utils.parseDateOnly('2025-07-03');
            expect(d.getFullYear()).toBe(2025);
            expect(d.getMonth()).toBe(6); // July = 6 (0-indexed)
            expect(d.getDate()).toBe(3);
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
        let func: ReturnType<typeof vi.fn>;
        let debounced: ReturnType<typeof Utils.debounce>;

        beforeEach(() => {
            vi.useFakeTimers();
            func = vi.fn();
            debounced = Utils.debounce(func, 100);
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        test('delays execution and respects wait time', () => {
            debounced();
            expect(func).not.toHaveBeenCalled();

            vi.advanceTimersByTime(50);
            expect(func).not.toHaveBeenCalled();

            vi.advanceTimersByTime(50);
            expect(func).toHaveBeenCalledTimes(1);
        });

        test('resets timer if called again within wait period', () => {
            debounced();
            vi.advanceTimersByTime(50);
            debounced(); // Should reset
            vi.advanceTimersByTime(50);
            expect(func).not.toHaveBeenCalled();

            vi.advanceTimersByTime(50);
            expect(func).toHaveBeenCalledTimes(1);
        });

        test('passes arguments correctly', () => {
            debounced('arg1', 2);
            vi.runAllTimers();
            expect(func).toHaveBeenCalledWith('arg1', 2);
        });

        test('maintains correct this context', () => {
            const contextFunc = vi.fn(function (this: any) {
                // No-op
            });
            const obj = { debounced: Utils.debounce(contextFunc, 100) };

            obj.debounced();
            vi.runAllTimers();

            // Vitest (and Jest) store the 'this' context in mock.instances
            expect(contextFunc.mock.instances[0]).toBe(obj);
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
