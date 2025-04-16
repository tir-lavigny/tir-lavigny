<script setup lang="ts">
import AppEventCardAdmin from '@/components/AppEventCardAdmin.vue';
import AppPagination from '@/components/AppPagination.vue';
import { Muted } from '@/components/typography/texts';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { PaginatedEvents, type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Événements',
        href: route('events.index'),
    },
];

const props = defineProps<{
    paginatedEvents: PaginatedEvents;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters.search || '');

const handlePageChange = (page: number = 1) => {
    router.get(
        route('events.index'),
        {
            page: page,
            search: search.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

watch(search, (newValue) => {
    router.get(
        route('events.index'),
        {
            page: 1,
            search: newValue,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
});
</script>

<template>
    <Head title="Événements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="relative w-full max-w-sm items-center">
                <Input id="search" type="text" v-model="search" placeholder="Rechercher..." class="pl-10" />
                <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                    <Search class="text-muted-foreground size-6" />
                </span>
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
