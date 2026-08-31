<script setup lang="ts">
import { H1 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay, Season } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
    season: Season;
    operatingDays: OperatingDay[];
    filters: { detail?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapports', href: route('bo.register.reports.index') },
    { title: props.season.name, href: route('bo.register.reports.season', props.season.id) },
];

const detail = ref(props.filters.detail || 'balance');

const handleDetailChange = (value: unknown) => {
    if (typeof value !== 'string') return;
    detail.value = value;
    router.get(route('bo.register.reports.season', props.season.id), { detail: value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head :title="'Rapport ' + season.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Rapport — {{ season.name }}</H1>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <Select :model-value="detail" @update:model-value="handleDetailChange">
                        <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="balance">Balance</SelectItem>
                            <SelectItem value="tickets">Tickets</SelectItem>
                            <SelectItem value="items">Articles</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <a :href="route('bo.register.reports.season-export', { season: season.id, detail })" target="_blank">
                    <Button variant="outline"><Download class="mr-2 size-4" /> Exporter Excel</Button>
                </a>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
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
                        <TableCell class="font-medium">{{ formatDate(day.date, 'DD.MM.YYYY') }}</TableCell>
                        <TableCell class="text-right">{{ formatMoney(day.total_cash) }} CHF</TableCell>
                        <TableCell class="text-right">{{ formatMoney(day.total_twint) }} CHF</TableCell>
                        <TableCell>
                            <Badge :variant="day.closed_at ? 'secondary' : 'default'">
                                {{ day.closed_at ? 'Fermé' : 'Ouvert' }}
                            </Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <Muted v-else class="flex items-center justify-center">Aucune journée pour cette saison.</Muted>
        </div>
    </AppLayout>
</template>
