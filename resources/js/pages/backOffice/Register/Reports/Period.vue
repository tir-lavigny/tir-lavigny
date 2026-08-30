<script setup lang="ts">
import { H1 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    operatingDays: OperatingDay[];
    filters: { from?: string; to?: string; detail?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapports', href: '#' },
    { title: 'Période', href: route('bo.register.reports.period') },
];

const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const detail = ref(props.filters.detail || 'balance');

const handleFilter = () => {
    router.get(
        route('bo.register.reports.period'),
        { from: from.value, to: to.value, detail: detail.value },
        { preserveState: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Rapport par période" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Rapport par période</H1>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <div class="flex items-end gap-4">
                    <div class="grid gap-2">
                        <Label for="from">Du</Label>
                        <Input id="from" v-model="from" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="to">Au</Label>
                        <Input id="to" v-model="to" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Détail</Label>
                        <Select v-model="detail">
                            <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="balance">Balance</SelectItem>
                                <SelectItem value="tickets">Tickets</SelectItem>
                                <SelectItem value="items">Articles</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button @click="handleFilter">Filtrer</Button>
                </div>
            </div>

            <Table v-if="operatingDays.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead class="text-right">Espèces</TableHead>
                        <TableHead class="text-right">Twint</TableHead>
                        <TableHead>Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="day in operatingDays" :key="day.id">
                        <TableCell class="font-medium">{{ formatDate(day.date, 'dd.MM.yyyy') }}</TableCell>
                        <TableCell class="text-right">{{ day.total_cash.toFixed(2) }} CHF</TableCell>
                        <TableCell class="text-right">{{ day.total_twint.toFixed(2) }} CHF</TableCell>
                        <TableCell>
                            <Badge :variant="day.closed_at ? 'secondary' : 'default'">
                                {{ day.closed_at ? 'Fermé' : 'Ouvert' }}
                            </Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <Muted v-else class="flex items-center justify-center">Aucune journée pour cette période.</Muted>
        </div>
    </AppLayout>
</template>
