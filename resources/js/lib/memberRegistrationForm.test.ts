import { describe, expect, it } from 'vitest';
import { stepForRegistrationError } from './memberRegistrationForm';

describe('stepForRegistrationError', () => {
    it('maps identity fields to the first step', () => {
        expect(stepForRegistrationError('first_name')).toBe(1);
        expect(stepForRegistrationError('last_name')).toBe(1);
        expect(stepForRegistrationError('email')).toBe(1);
        expect(stepForRegistrationError('tel')).toBe(1);
        expect(stepForRegistrationError('avs')).toBe(1);
        expect(stepForRegistrationError('is_swiss_citizen')).toBe(1);
    });

    it('maps address fields to the second step', () => {
        expect(stepForRegistrationError('street')).toBe(2);
        expect(stepForRegistrationError('npa')).toBe(2);
        expect(stepForRegistrationError('city')).toBe(2);
        expect(stepForRegistrationError('canton')).toBe(2);
        expect(stepForRegistrationError('country')).toBe(2);
    });

    it('returns null for unknown fields', () => {
        expect(stepForRegistrationError('form_uuid')).toBeNull();
    });
});
