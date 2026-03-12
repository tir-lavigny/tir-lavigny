import { test } from '@/tests';
import { expect } from 'vitest';
import * as Utils from './utils';

test('should format date correctly', () => {
    const date = '2025-07-03Z19:35:00';
    const formattedDate = Utils.formatDate(date, 'dd/MM/yyyy');
    expect(formattedDate).toBe('01/01/2024');
});
