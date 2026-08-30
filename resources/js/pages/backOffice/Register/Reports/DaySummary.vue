<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay, SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();

const props = defineProps<{ operatingDay: OperatingDay }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapports', href: '#' },
    { title: 'Résumé', href: route('bo.register.reports.day-summary', props.operatingDay.id) },
];
</script>

<template>
    <Head :title="'Résumé journée ' + formatDate(operatingDay.date, 'dd.MM.yyyy')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Résumé du {{ formatDate(operatingDay.date, 'dd.MM.yyyy') }}</H1>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid grid-cols-3 gap-4">
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Total Espèces</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold">{{ operatingDay.total_cash.toFixed(2) }} CHF</p></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Total Twint</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold">{{ operatingDay.total_twint.toFixed(2) }} CHF</p></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Solde clôture</CardTitle></CardHeader>
                    <CardContent><p class="text-2xl font-bold">{{ operatingDay.closing_balance?.toFixed(2) ?? '—' }} CHF</p></CardContent>
                </Card>
            </div>

            <H3>Tickets ({{ operatingDay.tickets?.length ?? 0 }})</H3>

            <Table v-if="operatingDay.tickets?.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>#</TableHead>
                        <TableHead>Paiement</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="ticket in operatingDay.tickets" :key="ticket.id">
                        <TableCell class="font-medium">{{ ticket.id }}</TableCell>
                        <TableCell>
                            <Badge :variant="ticket.payment_method === 'cash' ? 'default' : 'secondary'">
                                {{ ticket.payment_method === 'cash' ? 'Espèces' : 'TWINT' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">{{ ticket.total.toFixed(2) }} CHF</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="text-sm text-muted-foreground">Aucun ticket.</p>
        </div>
    </AppLayout>
</template>
