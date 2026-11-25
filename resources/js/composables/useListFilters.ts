import { router } from '@inertiajs/vue3';
import { onScopeDispose, ref, watch, type Ref } from 'vue';

type FilterValue = string | number | boolean | null | undefined;

export function useListFilters<T extends Record<string, FilterValue>>(
    routeName: string,
    initialFilters: T,
    debounceMs = 300,
    routeParams: Record<string, string | number> = {},
) {
    const filters = ref({ ...initialFilters }) as Ref<T>;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const clearPendingVisit = (): void => {
        if (!timer) {
            return;
        }

        clearTimeout(timer);
        timer = undefined;
    };

    const visit = (page = 1): void => {
        clearPendingVisit();

        const url = Object.keys(routeParams).length > 0 ? route(routeName, routeParams) : route(routeName);

        router.get(url, { page, ...filters.value }, { preserveState: true, preserveScroll: true });
    };

    watch(
        filters,
        () => {
            clearPendingVisit();
            timer = setTimeout(() => visit(1), debounceMs);
        },
        { deep: true },
    );

    onScopeDispose(clearPendingVisit);

    return { filters, visit };
}
