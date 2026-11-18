export const MEMBER_REGISTRATION_FIELD_STEPS: Record<string, number> = {
    first_name: 1,
    last_name: 1,
    is_swiss_citizen: 1,
    avs: 1,
    email: 1,
    tel: 1,
    street: 2,
    npa: 2,
    city: 2,
    canton: 2,
    country: 2,
};

export function stepForRegistrationError(field: string): number | null {
    return MEMBER_REGISTRATION_FIELD_STEPS[field] ?? null;
}
