<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedOperatingDays } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Journées', href: route('bo.register.operating-days.index') }];

const props = defineProps<{
    operatingDays: PaginatedOperatingDays;
    filters: { season_id?: string };
}>();

const handlePageChange = (p: number = 1) => {
    router.get(
        route('bo.register.operating-days.index'),
        { page: p, season_id: props.filters.season_id },
        { preserveState: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Journées d'opération" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Journées d'opération</H3>
                    </div>
                </div>
                <div v-for="day in operatingDays.data" :key="day.id" class="mb-4">
                    <Link
                        :href="route('bo.register.operating-days.show', day.id)"
                        class="hover:bg-accent flex items-center justify-between rounded-lg border p-4"
                    >
                        <div>
                            <p class="font-medium hover:underline">{{ formatDate(day.date, 'dd.MM.yyyy') }}</p>
                            <p class="text-muted-foreground text-sm">
                                <span v-if="day.season">{{ day.season.name }} · </span>
                                Espèces: {{ day.total_cash.toFixed(2) }} CHF · Twint: {{ day.total_twint.toFixed(2) }} CHF
                            </p>
                        </div>
                        <Badge :variant="day.closed_at ? 'secondary' : 'default'">
                            {{ day.closed_at ? 'Fermé' : 'Ouvert' }}
                        </Badge>
                    </Link>
                </div>
                <Muted v-if="operatingDays.data?.length" class="my-4 flex items-center justify-center">
                    {{ operatingDays.total }} journée(s) trouvée(s)
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucune journée trouvée</Muted>
                <AppPagination
                    :items-per-page="operatingDays.per_page"
                    :total-items="operatingDays.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="operatingDays.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
