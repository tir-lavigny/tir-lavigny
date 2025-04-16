<script setup lang="ts">
import AppDatePicker from '@/components/AppDatePicker.vue';
import AppEventCardAdmin from '@/components/AppEventCardAdmin.vue';
import AppPagination from '@/components/AppPagination.vue';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { PaginatedEvents, type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search, Undo2 } from 'lucide-vue-next';
import { ref, useTemplateRef, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Événements',
        href: route('bo.events.index'),
    },
];

const props = defineProps<{
    paginatedEvents: PaginatedEvents;
    filters: {
        search?: string;
        start_before?: string;
        end_before?: string;
    };
}>();

const datePicker = useTemplateRef('datePicker');

const filters = ref({
    search: props.filters.search || '',
    startBefore: props.filters.start_before || '',
    endBefore: props.filters.end_before || '',
});

const handleDateReset = () => {
    filters.value.startBefore = '';
    datePicker.value?.reset();
};

const handlePageChange = (page: number = 1) => {
    router.get(
        route('bo.events.index'),
        {
            page: page,
            search: filters.value.search,
            start_before: filters.value.startBefore,
            end_before: filters.value.endBefore,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

watch(
    filters,
    (newFilters) => {
        router.get(
            route('bo.events.index'),
            {
                page: 1,
                search: newFilters.search,
                start_before: newFilters.startBefore,
                end_before: newFilters.endBefore,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    },
    { deep: true },
);
</script>

<template>
    <Head title="Événements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex w-full flex-col items-center justify-between gap-6 lg:flex-row">
                <div class="relative w-full items-center lg:max-w-sm">
                    <Input id="search" type="text" v-model="filters.search" placeholder="Rechercher..." class="pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search class="text-muted-foreground size-6" />
                    </span>
                </div>
                <div class="flex w-full items-center gap-2 lg:w-auto">
                    <span class="text-muted-foreground">Avant le</span>
                    <AppDatePicker
                        ref="datePicker"
                        placeholder="Sélectionner une date"
                        @update:value="(newDate) => (filters.startBefore = newDate)"
                    />
                    <Button v-if="filters.startBefore" size="icon" variant="outline" @click="handleDateReset">
                        <Undo2 />
                    </Button>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <AppEventCardAdmin v-for="event in paginatedEvents.data" :key="event.id" :event="event" class="mb-4" />
                <Muted v-if="paginatedEvents.data" class="my-4 flex items-center justify-center">
                    {{ paginatedEvents.total }} événements trouvés
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center"> Aucun événement trouvé </Muted>
                <AppPagination
                    :items-per-page="paginatedEvents.per_page"
                    :total-items="paginatedEvents.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="paginatedEvents.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
