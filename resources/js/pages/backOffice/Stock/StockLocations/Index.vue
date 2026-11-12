<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedStockLocations, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Emplacements', href: route('bo.stock.locations.index') }];

defineProps<{
    locations: PaginatedStockLocations;
}>();

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.stock.locations.index'), { page: p }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Emplacements de stock" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Emplacements de stock</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage stock')">
                            <TooltipTrigger>
                                <Link :href="route('bo.stock.locations.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Ajouter un emplacement</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <Table v-if="locations.data?.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nom</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="location in locations.data" :key="location.id">
                            <TableCell class="font-medium">{{ location.name }}</TableCell>
                            <TableCell class="text-muted-foreground max-w-[300px] truncate text-sm">{{ location.description ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge :variant="location.active ? 'secondary' : 'destructive'">
                                    {{ location.active ? 'Actif' : 'Inactif' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Link :href="route('bo.stock.locations.show', location.id)">
                                    <Button size="sm" variant="outline">Voir</Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Muted v-if="locations.data?.length" class="my-4 flex items-center justify-center">
                    {{ locations.total }} emplacement(s) trouvé(s)
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucun emplacement trouvé</Muted>
                <AppPagination
                    :items-per-page="locations.per_page"
                    :total-items="locations.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :page="locations.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
