<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedStockTransfers, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Transferts', href: route('bo.stock.transfers.index') }];

defineProps<{
    transfers: PaginatedStockTransfers;
}>();

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.stock.transfers.index'), { page: p }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Transferts de stock" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Transferts de stock</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage stock')">
                            <TooltipTrigger>
                                <Link :href="route('bo.stock.transfers.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Créer un transfert</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <Table v-if="transfers.data?.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Origine</TableHead>
                            <TableHead>Destination</TableHead>
                            <TableHead>Raison</TableHead>
                            <TableHead class="text-right">Lignes</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="transfer in transfers.data" :key="transfer.id">
                            <TableCell>{{ formatDate(transfer.created_at, 'DD.MM.YYYY HH:mm') }}</TableCell>
                            <TableCell class="font-medium">{{ transfer.from_location?.name ?? '-' }}</TableCell>
                            <TableCell class="font-medium">{{ transfer.to_location?.name ?? '-' }}</TableCell>
                            <TableCell class="text-muted-foreground max-w-[200px] truncate text-sm">{{ transfer.reason ?? '-' }}</TableCell>
                            <TableCell class="text-right">{{ transfer.stock_transfer_lines?.length ?? 0 }}</TableCell>
                            <TableCell class="text-right">
                                <Link :href="route('bo.stock.transfers.show', transfer.id)">
                                    <Button size="sm" variant="outline">Voir</Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Muted v-if="transfers.data?.length" class="my-4 flex items-center justify-center">
                    {{ transfers.total }} transfert(s) trouvé(s)
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucun transfert trouvé</Muted>
                <AppPagination
                    :items-per-page="transfers.per_page"
                    :total-items="transfers.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :page="transfers.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
