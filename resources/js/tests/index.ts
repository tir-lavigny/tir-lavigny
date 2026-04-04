import { User } from '@/types';
import { test as baseTest } from 'vitest';
import { createRole, createUser } from './fixtures';

export * from './fixtures';

export const test = baseTest.extend<{
    user: User;
    admin: User;
}>({
    user: createUser({ name: 'User' }),
    admin: createUser({
        name: 'Admin',
        roles: [createRole({ name: 'admin' })],
    }),
});
