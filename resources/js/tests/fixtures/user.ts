import type { User } from '@/types';
import { createRoleFixture } from './role';

export function createUserFixture(overrides: Partial<User> = {}): User {
    return {
        id: 1,
        name: 'John Doe',
        email: 'john@gmail.com',
        email_verified_at: '2024-01-01T00:00:00Z',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        roles: [createRoleFixture('user', [])],
        ...overrides,
    };
}
