import { test } from '@/tests';
import { expect } from 'vitest';
import * as Utils from './utils';

test('should format date correctly', () => {
    const date = '2025-07-03T19:35:00';
    const formattedDate = Utils.formatDate(date, 'DD/MM/YYYY');
    expect(formattedDate).toBe('03/07/2025');
});
