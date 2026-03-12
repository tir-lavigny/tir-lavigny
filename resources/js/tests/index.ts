import { createUserFixture } from '@/tests/fixtures/user';
import { User } from '@/types';
import { test as baseTest } from 'vitest';

export const test = baseTest.extend<{
    user: User;
}>({
    user: createUserFixture(),
});
