<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import { PaginatedEventChains, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Eye, PenBox, Plus, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Séries d'événements",
        href: route('bo.event-chains.index'),
    },
];

const props = defineProps<{
    paginatedChains: PaginatedEventChains;
    filters?: { search?: string };
}>();

const search = ref(props.filters?.search || '');

const recurrenceLabel: Record<string, string> = {
    daily: 'Quotidien',
    weekly: 'Hebdomadaire',
    biweekly: 'Bihebdomadaire',
    monthly: 'Mensuel',
    custom: 'Personnalisé',
};

const recurrenceBadgeVariant = (type: string): 'default' | 'secondary' | 'outline' => {
    const map: Record<string, 'default' | 'secondary' | 'outline'> = {
        daily: 'default',
        weekly: 'secondary',
        biweekly: 'secondary',
        monthly: 'outline',
        custom: 'outline',
    };
    return map[type] ?? 'default';
};

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.event-chains.index'), { page: p, search: search.value }, { preserveState: true, preserveScroll: true });
};

watch(search, (val) => {
    router.get(route('bo.event-chains.index'), { page: 1, search: val }, { preserveState: true, preserveScroll: true });
});
</script>

<template>
    <Head title="Séries d'événements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Search bar -->
            <div class="flex w-full flex-col items-center justify-between gap-6 lg:flex-row">
                <div class="relative w-full items-center lg:max-w-sm">
                    <Input id="search" type="text" v-model="search" placeholder="Rechercher..." class="pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search class="text-muted-foreground size-6" />
                    </span>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Séries d'événements</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'create event chain')">
                            <TooltipTrigger>
                                <Link :href="route('bo.event-chains.create')">
                                    <Button size="icon">
                                        <Plus />
                                    </Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Créer une nouvelle série</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>

                <!-- Chain cards -->
                <div v-if="paginatedChains.data && paginatedChains.data.length > 0" class="flex flex-col gap-3">
                    <div
                        v-for="chain in paginatedChains.data"
                        :key="chain.id"
                        class="border-sidebar-border/70 dark:border-sidebar-border hover:bg-accent/50 flex flex-col gap-3 rounded-xl border p-4 transition-colors sm:flex-row sm:items-center sm:justify-between"
                    >
                        <!-- Left: info -->
                        <div class="flex items-start gap-3">
                            <CalendarDays class="text-muted-foreground mt-0.5 size-5 shrink-0" />
                            <div class="flex flex-col gap-1">
                                <span class="font-semibold">{{ chain.title }}</span>
                                <div class="text-muted-foreground flex flex-wrap items-center gap-2 text-sm">
                                    <span>{{ formatDate(chain.start_date, 'DD MMM YYYY') }} → {{ formatDate(chain.end_date, 'DD MMM YYYY') }}</span>
                                    <span>·</span>
                                    <span>{{ chain.start_time.slice(0, 5) }}{{ chain.end_time ? ' - ' + chain.end_time.slice(0, 5) : '' }}</span>
                                    <span v-if="chain.location">· {{ chain.location.name }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: badges + actions -->
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <Badge :variant="recurrenceBadgeVariant(chain.recurrence_type)">
                                {{ recurrenceLabel[chain.recurrence_type] ?? chain.recurrence_type }}
                            </Badge>
                            <Badge variant="secondary"> {{ chain.events_count }} événement{{ (chain.events_count ?? 0) > 1 ? 's' : '' }} </Badge>

                            <div class="flex gap-2">
                                <Tooltip>
                                    <TooltipTrigger>
                                        <Link :href="route('bo.event-chains.show', chain.id)">
                                            <Button size="icon" variant="outline">
                                                <Eye />
                                            </Button>
                                        </Link>
                                    </TooltipTrigger>
                                    <TooltipContent><p>Voir les détails</p></TooltipContent>
                                </Tooltip>
                                <Tooltip v-if="hasPermissionTo(user, 'edit event chain')">
                                    <TooltipTrigger>
                                        <Link :href="route('bo.event-chains.edit', chain.id)">
                                            <Button size="icon" variant="secondary">
                                                <PenBox />
                                            </Button>
                                        </Link>
                                    </TooltipTrigger>
                                    <TooltipContent><p>Modifier la série</p></TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                    </div>
                </div>

                <Muted v-if="paginatedChains.total" class="my-4 flex items-center justify-center">
                    {{ paginatedChains.total }} série{{ paginatedChains.total > 1 ? 's' : '' }} trouvée{{ paginatedChains.total > 1 ? 's' : '' }}
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucune série trouvée</Muted>

                <AppPagination
                    :items-per-page="paginatedChains.per_page"
                    :total-items="paginatedChains.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="paginatedChains.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
