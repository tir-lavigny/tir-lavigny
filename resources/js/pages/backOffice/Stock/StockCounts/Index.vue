<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedStockCounts, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inventaires', href: route('bo.stock.counts.index') }];

defineProps<{
    counts: PaginatedStockCounts;
}>();

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.stock.counts.index'), { page: p }, { preserveState: true, preserveScroll: true });
};

const countTypeLabel: Record<string, string> = {
    initial: 'Initial',
    adjustment: 'Ajustement',
    final: 'Final',
};
</script>

<template>
    <Head title="Inventaires" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Inventaires</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage stock')">
                            <TooltipTrigger>
                                <Link :href="route('bo.stock.counts.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Créer un inventaire</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <Table v-if="counts.data?.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Lieu</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead>Créé le</TableHead>
                            <TableHead>Complété le</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="count in counts.data" :key="count.id">
                            <TableCell class="font-medium">{{ count.stock_location?.name ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge variant="secondary">{{ countTypeLabel[count.type] ?? count.type }}</Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="count.completed_at ? 'secondary' : 'default'">
                                    {{ count.completed_at ? 'Terminé' : 'En cours' }}
                                </Badge>
                            </TableCell>
                            <TableCell>{{ formatDate(count.created_at, 'DD.MM.YYYY HH:mm') }}</TableCell>
                            <TableCell>{{ count.completed_at ? formatDate(count.completed_at, 'DD.MM.YYYY HH:mm') : '—' }}</TableCell>
                            <TableCell class="text-right">
                                <Link :href="route('bo.stock.counts.show', count.id)">
                                    <Button size="sm" variant="outline">Voir</Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Muted v-if="counts.data?.length" class="my-4 flex items-center justify-center"> {{ counts.total }} inventaire(s) trouvé(s) </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucun inventaire trouvé</Muted>
                <AppPagination
                    :items-per-page="counts.per_page"
                    :total-items="counts.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="counts.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
