import { createPermission, createRole, test } from '@/tests';
import { expect } from 'vitest';
import * as Utils from './utils';

test('should format date correctly', () => {
    const date = '2025-07-03T19:35:00';
    const formattedDate = Utils.formatDate(date, 'DD/MM/YYYY');
    expect(formattedDate).toBe('03/07/2025');
});

test('hasRole should return true if user has the role', ({ user }) => {
    expect(Utils.hasRole(user, 'user')).toBe(true);
    expect(Utils.hasRole(user, 'admin')).toBe(false);
});

test('hasPermissionTo should return true if user has the permission', ({ admin }) => {
    const permission = createPermission({ name: 'edit articles' });
    const role = createRole({ name: 'admin', permissions: [permission] });
    admin.roles = [role];

    expect(Utils.hasPermissionTo(admin, 'edit articles')).toBe(true);
    expect(Utils.hasPermissionTo(admin, 'delete articles')).toBe(false);
});
