import { describe, expect, it } from 'vitest';
import { SWISS_CANTONS } from './helpers';

describe('SWISS_CANTONS', () => {
    it('should contain all 26 Swiss cantons', () => {
        expect(SWISS_CANTONS).toHaveLength(26);
    });
});
