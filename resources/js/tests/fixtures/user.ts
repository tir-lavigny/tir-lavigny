import type { User } from '@/types';
import { createRole } from './role';

export function createUser(overrides: Partial<User> = {}): User {
    const id = overrides.id ?? Math.floor(Math.random() * 1000);
    return {
        id,
        name: 'John Doe',
        email: 'john@example.com',
        email_verified_at: new Date().toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        roles: [createRole({ name: 'user' })],
        ...overrides,
    };
}
