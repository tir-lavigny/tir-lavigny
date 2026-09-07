<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import AppEventCardAdmin from '@/components/back-office/AppEventCardAdmin.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import { PaginatedEvents, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref, watch } from 'vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

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
        past?: boolean;
    };
}>();

const filters = ref({
    search: props.filters.search || '',
    past: props.filters.past || false,
});

const handlePageChange = (page: number = 1) => {
    router.get(
        route('bo.events.index'),
        {
            page: page,
            search: filters.value.search,
            past: filters.value.past || undefined,
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
                past: newFilters.past || undefined,
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
            </div>
            <div class="flex w-full items-center gap-2">
                <Button :variant="filters.past ? 'outline' : 'default'" size="sm" @click="filters.past = false"> À venir </Button>
                <Button :variant="filters.past ? 'default' : 'outline'" size="sm" @click="filters.past = true"> Passés </Button>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Événements</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'create event')">
                            <TooltipTrigger>
                                <Link :href="route('bo.events.create')">
                                    <Button size="icon">
                                        <Plus />
                                    </Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Créer un nouvel événement</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <AppEventCardAdmin
                    v-for="event in paginatedEvents.data"
                    :key="event.id"
                    :event="event"
                    class="mb-4"
                    :actions="[
                        ...(hasPermissionTo(user, 'edit event') ? ['edit'] : []),
                        ...(hasPermissionTo(user, 'delete event') ? ['delete'] : []),
                    ]"
                />
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
