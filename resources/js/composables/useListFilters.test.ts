import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick } from 'vue';
import { useListFilters } from './useListFilters';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { get },
}));

describe('useListFilters', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal('route', (name: string, params?: Record<string, string | number>) =>
            params ? `/${name}/${Object.values(params).join('/')}` : `/${name}`,
        );
        get.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('debounces filter changes into one request to the first page', async () => {
        const scope = effectScope();
        const { filters } = scope.run(() => useListFilters('bo.members.index', { search: '' }))!;

        filters.value.search = 'b';
        await nextTick();
        filters.value.search = 'bi';
        await nextTick();
        vi.advanceTimersByTime(300);

        expect(get).toHaveBeenCalledTimes(1);
        expect(get).toHaveBeenCalledWith('/bo.members.index', { page: 1, search: 'bi' }, { preserveState: true, preserveScroll: true });

        scope.stop();
    });

    it('visits a page immediately with current filters and cancels a pending search', async () => {
        const scope = effectScope();
        const { filters, visit } = scope.run(() => useListFilters('bo.members.index', { search: '' }))!;

        filters.value.search = 'bi';
        await nextTick();
        visit(2);
        vi.advanceTimersByTime(300);

        expect(get).toHaveBeenCalledTimes(1);
        expect(get).toHaveBeenCalledWith('/bo.members.index', { page: 2, search: 'bi' }, { preserveState: true, preserveScroll: true });

        scope.stop();
    });

    it('includes route parameters in the visit URL', async () => {
        const scope = effectScope();
        const { filters } = scope.run(() =>
            useListFilters('bo.invoicing.membership-fee-campaigns.show', { search: '' }, 300, { membershipFeeCampaign: 2 }),
        )!;

        filters.value.search = 'alice';
        await nextTick();
        vi.advanceTimersByTime(300);

        expect(get).toHaveBeenCalledWith(
            '/bo.invoicing.membership-fee-campaigns.show/2',
            { page: 1, search: 'alice' },
            { preserveState: true, preserveScroll: true },
        );

        scope.stop();
    });
});
