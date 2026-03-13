import { vi } from 'vitest';

// Mock Ziggy's route function
vi.mock('ziggy-js', () => ({
    default: (name: string) => {
        return `/${name}`;
    },
}));

// Mock Inertia
vi.mock('@inertiajs/vue3', async () => {
    const actual = (await vi.importActual('@inertiajs/vue3')) as any;
    return {
        ...actual,
        usePage: () => ({
            props: {
                auth: {
                    user: null,
                },
            },
        }),
    };
});
