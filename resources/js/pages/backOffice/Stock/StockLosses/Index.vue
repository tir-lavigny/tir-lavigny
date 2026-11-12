<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedStockLosses, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pertes', href: route('bo.stock.losses.index') }];

defineProps<{
    losses: PaginatedStockLosses;
}>();

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.stock.losses.index'), { page: p }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Pertes de stock" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Pertes de stock</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'record stock loss')">
                            <TooltipTrigger>
                                <Link :href="route('bo.stock.losses.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Enregistrer une perte</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <Table v-if="losses.data?.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Lieu</TableHead>
                            <TableHead>Produit</TableHead>
                            <TableHead class="text-right">Quantité</TableHead>
                            <TableHead>Raison</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="loss in losses.data" :key="loss.id">
                            <TableCell>{{ formatDate(loss.created_at, 'DD.MM.YYYY HH:mm') }}</TableCell>
                            <TableCell class="font-medium">{{ loss.stock_location?.name ?? '-' }}</TableCell>
                            <TableCell class="font-medium">{{ loss.product?.name ?? '-' }}</TableCell>
                            <TableCell class="text-right">{{ loss.quantity }}</TableCell>
                            <TableCell class="text-muted-foreground max-w-[200px] truncate text-sm">{{ loss.reason ?? '-' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Muted v-if="losses.data?.length" class="my-4 flex items-center justify-center"> {{ losses.total }} perte(s) trouvée(s) </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucune perte trouvée</Muted>
                <AppPagination
                    :items-per-page="losses.per_page"
                    :total-items="losses.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :page="losses.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
