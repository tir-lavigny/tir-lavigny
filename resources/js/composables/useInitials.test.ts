import { describe, expect, it } from 'vitest';
import { getInitials, useInitials } from './useInitials';

describe('useInitials', () => {
    describe('getInitials', () => {
        it('returns an empty string if no name is provided', () => {
            expect(getInitials()).toBe('');
            expect(getInitials('')).toBe('');
        });

        it('returns the first letter uppercase for a single name', () => {
            expect(getInitials('john')).toBe('J');
            expect(getInitials('  mary  ')).toBe('M');
        });

        it('returns the first and last initials for multiple names', () => {
            expect(getInitials('John Doe')).toBe('JD');
            expect(getInitials('John Quincy Adams')).toBe('JA');
        });

        it('handles multiple spaces between names', () => {
            expect(getInitials('John    Doe')).toBe('JD');
        });

        it('converts initials to uppercase', () => {
            expect(getInitials('john doe')).toBe('JD');
        });

        it('handles special characters at the beginning of names', () => {
            expect(getInitials('@john #doe')).toBe('@#');
        });

        it('returns an empty string for string with only spaces', () => {
            expect(getInitials('   ')).toBe('');
        });
    });

    describe('useInitials composable', () => {
        it('returns an object containing getInitials', () => {
            const { getInitials: initialsFn } = useInitials();
            expect(initialsFn).toBe(getInitials);
        });
    });
});
