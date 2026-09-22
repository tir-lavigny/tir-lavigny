<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{ operatingDay: OperatingDay }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapports', href: route('bo.register.reports.index') },
    { title: formatDate(props.operatingDay.date, 'DD.MM.YYYY'), href: route('bo.register.reports.day-detail', props.operatingDay.id) },
    { title: 'Résumé', href: route('bo.register.reports.day-summary', props.operatingDay.id) },
];
</script>

<template>
    <Head :title="'Résumé journée ' + formatDate(operatingDay.date, 'DD.MM.YYYY')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Résumé du {{ formatDate(operatingDay.date, 'DD.MM.YYYY') }}</H1>
        </div>
        <div class="mx-4 mb-4 flex gap-1 rounded-lg border p-1">
            <Link
                :href="route('bo.register.reports.day-summary', operatingDay.id)"
                class="bg-primary text-primary-foreground rounded-md px-3 py-1.5 text-sm font-medium"
            >
                Résumé
            </Link>
            <Link
                :href="route('bo.register.reports.day-detail', operatingDay.id)"
                class="text-muted-foreground hover:text-foreground rounded-md px-3 py-1.5 text-sm font-medium"
            >
                Détail
            </Link>
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
                    <TableRow v-for="ticket in operatingDay.tickets" :key="ticket.id" :class="{ 'opacity-50': ticket.deleted_at }">
                        <TableCell class="font-medium">{{ ticket.id }}</TableCell>
                        <TableCell>
                            <Badge :variant="ticket.payment_method === 'cash' ? 'default' : 'secondary'">
                                {{ ticket.payment_method === 'cash' ? 'Espèces' : 'TWINT' }}
                            </Badge>
                            <Badge v-if="ticket.deleted_at" variant="destructive" class="ml-1">Remboursé</Badge>
                        </TableCell>
                        <TableCell class="text-right" :class="{ 'line-through': ticket.deleted_at }">{{ formatMoney(ticket.total) }} CHF</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="text-muted-foreground text-sm">Aucun ticket.</p>
        </div>
    </AppLayout>
</template>
