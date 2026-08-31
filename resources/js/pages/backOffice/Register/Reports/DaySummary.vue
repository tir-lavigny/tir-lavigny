<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ operatingDay: OperatingDay }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapports', href: '#' },
    { title: 'Résumé', href: route('bo.register.reports.day-summary', props.operatingDay.id) },
];
</script>

<template>
    <Head :title="'Résumé journée ' + formatDate(operatingDay.date, 'DD.MM.YYYY')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Résumé du {{ formatDate(operatingDay.date, 'DD.MM.YYYY') }}</H1>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid grid-cols-3 gap-4">
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Total Espèces</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ formatMoney(operatingDay.total_cash) }} CHF</p></CardContent
                    >
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Total Twint</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ formatMoney(operatingDay.total_twint) }} CHF</p></CardContent
                    >
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Solde clôture</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">
                            {{ operatingDay.closing_balance != null ? formatMoney(operatingDay.closing_balance) : '—' }} CHF
                        </p></CardContent
                    >
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
                        <TableCell class="text-right">{{ formatMoney(ticket.total) }} CHF</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="text-muted-foreground text-sm">Aucun ticket.</p>
        </div>
    </AppLayout>
</template>
