<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay, Product, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Lock, Unlock } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    operatingDay: OperatingDay;
    correctionProduct: Product | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journées', href: route('bo.register.operating-days.index') },
    { title: formatDate(props.operatingDay.date, 'DD.MM.YYYY'), href: route('bo.register.operating-days.show', props.operatingDay.id) },
];

const closeForm = useForm({ status: 'closed' });
const reopenForm = useForm({ status: 'open' });

const isOpen = computed(() => !props.operatingDay.closed_at);

const handleClose = () => {
    closeForm.put(route('bo.register.operating-days.update', props.operatingDay.id), {
        onSuccess: () => toast.success('Journée fermée.'),
        onError: () => toast.error('Erreur lors de la fermeture.'),
    });
};

const handleReopen = () => {
    reopenForm.put(route('bo.register.operating-days.update', props.operatingDay.id), {
        onSuccess: () => toast.success('Journée rouverte.'),
        onError: () => toast.error('Erreur lors de la réouverture.'),
    });
};
</script>

<template>
    <Head :title="'Journée ' + formatDate(operatingDay.date, 'DD.MM.YYYY')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <div class="flex items-center gap-3">
                <H1>Journée du {{ formatDate(operatingDay.date, 'dddd DD MMMM YYYY') }}</H1>
                <Badge :variant="isOpen ? 'default' : 'secondary'">{{ isOpen ? 'Ouverte' : 'Fermée' }}</Badge>
            </div>
            <div class="flex gap-2">
                <Button
                    v-if="isOpen && hasPermissionTo(user, 'close day')"
                    variant="destructive"
                    @click="handleClose"
                    :disabled="closeForm.processing"
                >
                    <Lock class="mr-2 size-4" /> Fermer
                </Button>
                <Button
                    v-if="!isOpen && hasPermissionTo(user, 'reopen day')"
                    variant="outline"
                    @click="handleReopen"
                    :disabled="reopenForm.processing"
                >
                    <Unlock class="mr-2 size-4" /> Rouvrir
                </Button>
            </div>
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
                    <CardContent>
                        <p class="text-2xl font-bold">{{ formatMoney(operatingDay.closing_balance) }} CHF</p>
                    </CardContent>
                </Card>
            </div>

            <Separator />

            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Remarque</H3>
                <div class="mt-4">
                    <p v-if="operatingDay.cash_remark" class="text-sm">{{ operatingDay.cash_remark }}</p>
                    <p v-else class="text-muted-foreground text-sm">Aucune remarque</p>
                </div>
            </div>

            <Separator />

            <H3>Tickets ({{ operatingDay.tickets?.length ?? 0 }})</H3>

            <Table v-if="operatingDay.tickets?.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>#</TableHead>
                        <TableHead>Vendu à</TableHead>
                        <TableHead>Paiement</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="ticket in operatingDay.tickets" :key="ticket.id">
                        <TableCell class="font-medium">{{ ticket.id }}</TableCell>
                        <TableCell>{{ formatDate(ticket.sold_at, 'HH:mm') }}</TableCell>
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
