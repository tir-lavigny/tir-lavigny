import type { Permission, Role } from '@/types';

export function createPermissionFixture(name: string, overrides: Omit<Partial<Permission>, 'name'> = {}): Permission {
    return {
        id: 1,
        name,
        guard_name: 'web',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        pivot: {
            role_id: 1,
            permission_id: 1,
        },
        ...overrides,
    };
}

export function createRoleFixture(name: string, permissions: Permission[], overrides: Omit<Partial<Role>, 'name' | 'permissions'> = {}): Role {
    return {
        id: 1,
        name,
        guard_name: 'web',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        pivot: {
            model_type: '',
            model_id: 0,
            role_id: 1,
        },
        permissions,
        ...overrides,
    };
}
