import { config } from '@vue/test-utils';
import { vi } from 'vitest';

// Mock Ziggy's route function
const routeMock = vi.fn().mockImplementation((name) => {
    if (name) return 'https://example.com/mock-url';
    return {
        current: vi.fn().mockReturnValue(true),
        params: {},
    };
});

// Global mock for route
vi.stubGlobal('route', routeMock);

// Configure Vue Test Utils to provide route globally
config.global.plugins = [
    {
        install(app) {
            app.config.globalProperties.route = routeMock;
        },
    },
];

// Mock Leaflet
const leafletMock = {
    map: vi.fn().mockReturnValue({
        setView: vi.fn().mockReturnThis(),
        removeLayer: vi.fn().mockReturnThis(),
        addLayer: vi.fn().mockReturnThis(),
        on: vi.fn().mockReturnThis(),
        off: vi.fn().mockReturnThis(),
    }),
    tileLayer: vi.fn().mockReturnValue({
        addTo: vi.fn().mockReturnThis(),
    }),
    marker: vi.fn().mockReturnValue({
        addTo: vi.fn().mockReturnThis(),
        bindPopup: vi.fn().mockReturnThis(),
        openPopup: vi.fn().mockReturnThis(),
    }),
    icon: vi.fn().mockReturnValue({}),
    popup: vi.fn().mockReturnValue({
        setContent: vi.fn().mockReturnThis(),
    }),
    Control: {
        extend: vi.fn(),
    },
};

vi.mock('leaflet', () => ({
    default: leafletMock,
    ...leafletMock,
}));

// Mock Inertia
vi.mock('@inertiajs/vue3', async () => {
    const actual = (await vi.importActual('@inertiajs/vue3')) as any;
    return {
        ...actual,
        usePage: () => ({
            url: '/',
            component: '',
            props: {
                auth: {
                    user: null,
                },
                ziggy: {
                    location: 'http://localhost/',
                },
                sidebarOpen: false,
            },
            version: null,
            scrollRegions: [],
            rememberedState: {},
        }),
        useForm: vi.fn().mockImplementation((data) => ({
            ...data,
            processing: false,
            progress: null,
            errors: {},
            isDirty: false,
            recentlySuccessful: false,
            wasSuccessful: false,
            delete: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
            setError: vi.fn(),
            transform: vi.fn().mockReturnThis(),
            submit: vi.fn(),
        })),
    };
});
