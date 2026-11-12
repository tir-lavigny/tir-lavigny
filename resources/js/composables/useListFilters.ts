import { router } from '@inertiajs/vue3';
import { onScopeDispose, ref, watch, type Ref } from 'vue';

type FilterValue = string | number | boolean | null | undefined;

export function useListFilters<T extends Record<string, FilterValue>>(routeName: string, initialFilters: T, debounceMs = 300) {
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

        router.get(route(routeName), { page, ...filters.value }, { preserveState: true, preserveScroll: true });
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
