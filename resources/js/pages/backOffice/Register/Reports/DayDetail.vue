<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ operatingDay: OperatingDay }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapports', href: '#' },
    { title: 'Journée', href: route('bo.register.reports.day-detail', props.operatingDay.id) },
];
</script>

<template>
    <Head :title="'Détail journée ' + formatDate(operatingDay.date, 'dd.MM.yyyy')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Détail du {{ formatDate(operatingDay.date, 'dd.MM.yyyy') }}</H1>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid grid-cols-3 gap-4">
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Total Espèces</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ operatingDay.total_cash.toFixed(2) }} CHF</p></CardContent
                    >
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Total Twint</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ operatingDay.total_twint.toFixed(2) }} CHF</p></CardContent
                    >
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Solde clôture</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ operatingDay.closing_balance?.toFixed(2) ?? '—' }} CHF</p></CardContent
                    >
                </Card>
            </div>

            <div
                v-for="ticket in operatingDay.tickets"
                :key="ticket.id"
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <H3 class="text-base">Ticket #{{ ticket.id }}</H3>
                        <Badge :variant="ticket.payment_method === 'cash' ? 'default' : 'secondary'">
                            {{ ticket.payment_method === 'cash' ? 'Espèces' : 'TWINT' }}
                        </Badge>
                    </div>
                    <span class="font-bold">{{ ticket.total.toFixed(2) }} CHF</span>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Produit</TableHead>
                            <TableHead class="text-center">Qté</TableHead>
                            <TableHead class="text-right">Prix unitaire</TableHead>
                            <TableHead class="text-right">Sous-total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in ticket.ticketItems" :key="item.id">
                            <TableCell>{{ item.product?.name ?? '—' }}</TableCell>
                            <TableCell class="text-center">{{ item.quantity }}</TableCell>
                            <TableCell class="text-right">{{ item.unit_price.toFixed(2) }} CHF</TableCell>
                            <TableCell class="text-right">{{ item.subtotal.toFixed(2) }} CHF</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <p v-if="!operatingDay.tickets?.length" class="text-muted-foreground text-sm">Aucun ticket.</p>
        </div>
    </AppLayout>
</template>
