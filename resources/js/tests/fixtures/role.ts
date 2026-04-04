import type { Permission, Role } from '@/types';

export function createPermission(overrides: Partial<Permission> = {}): Permission {
    const id = overrides.id ?? Math.floor(Math.random() * 1000);
    return {
        id,
        name: 'edit articles',
        guard_name: 'web',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        pivot: {
            role_id: 1,
            permission_id: id,
        },
        ...overrides,
    };
}

export function createRole(overrides: Partial<Role> = {}): Role {
    const id = overrides.id ?? Math.floor(Math.random() * 1000);
    return {
        id,
        name: 'user',
        guard_name: 'web',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        pivot: {
            model_type: 'App\\Models\\User',
            model_id: 1,
            role_id: id,
        },
        permissions: [],
        ...overrides,
    };
}
