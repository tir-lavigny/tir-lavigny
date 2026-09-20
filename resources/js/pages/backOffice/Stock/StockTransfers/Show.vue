<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, StockTransfer } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

const props = defineProps<{
    transfer: StockTransfer;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transferts', href: route('bo.stock.transfers.index') },
    { title: `Transfert #${props.transfer.id}`, href: route('bo.stock.transfers.show', props.transfer.id) },
];
</script>

<template>
    <Head :title="`Transfert #${transfer.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Transfert #{{ transfer.id }}</H1>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Détails</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Date</dt>
                            <dd class="font-medium">{{ formatDate(transfer.created_at, 'DD.MM.YYYY HH:mm') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Direction</dt>
                            <dd class="flex items-center gap-2 font-medium">
                                {{ transfer.from_location?.name ?? '-' }}
                                <ArrowRight class="text-muted-foreground size-4" />
                                {{ transfer.to_location?.name ?? '-' }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-muted-foreground text-sm">Raison</dt>
                            <dd class="font-medium">{{ transfer.reason ?? '-' }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <H3>Articles</H3>

            <Table v-if="transfer.stock_transfer_lines?.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Produit</TableHead>
                        <TableHead class="text-right">Quantité</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="line in transfer.stock_transfer_lines" :key="line.id">
                        <TableCell class="font-medium">{{ line.product?.name ?? '-' }}</TableCell>
                        <TableCell class="text-right">{{ line.quantity }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="text-muted-foreground text-sm">Aucun article.</p>
        </div>
    </AppLayout>
</template>
