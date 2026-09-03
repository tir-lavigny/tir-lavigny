<script setup lang="ts">
import TicketDetail from '@/components/back-office/Register/TicketDetail.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, OperatingDay, Product, SharedData, Ticket } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { createReusableTemplate, useMediaQuery } from '@vueuse/core';
import { Lock, Unlock } from '@lucide/vue';
import { computed, ref } from 'vue';
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

const isDesktop = useMediaQuery('(min-width: 768px)');
const selectedTicket = ref<Ticket | null>(null);
const isSheetOpen = ref(false);

const [DefineTicketContent, ReuseTicketContent] = createReusableTemplate<{ ticket: Ticket }>();

function openTicket(ticket: Ticket) {
    selectedTicket.value = ticket;
    isSheetOpen.value = true;
}

function handleClose() {
    closeForm.put(route('bo.register.operating-days.update', props.operatingDay.id), {
        onSuccess: () => toast.success('Journée fermée.'),
        onError: () => toast.error('Erreur lors de la fermeture.'),
    });
}

const handleReopen = () => {
    reopenForm.put(route('bo.register.operating-days.update', props.operatingDay.id), {
        onSuccess: () => toast.success('Journée rouverte.'),
        onError: () => toast.error('Erreur lors de la réouverture.'),
    });
};

function handleRefunded() {
    isSheetOpen.value = false;
    router.reload({ only: ['operatingDay'] });
}
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
                    <TableRow
                        v-for="ticket in operatingDay.tickets"
                        :key="ticket.id"
                        class="hover:bg-muted/50 cursor-pointer transition-colors"
                        :class="{ 'opacity-50': ticket.deleted_at }"
                        @click="openTicket(ticket)"
                    >
                        <TableCell class="font-medium">
                            {{ ticket.id }}
                            <Badge v-if="ticket.deleted_at" variant="destructive" class="ml-2">Remboursé</Badge>
                        </TableCell>
                        <TableCell>{{ formatDate(ticket.sold_at, 'HH:mm') }}</TableCell>
                        <TableCell>
                            <Badge :variant="ticket.payment_method === 'cash' ? 'default' : 'secondary'">
                                {{ ticket.payment_method === 'cash' ? 'Espèces' : 'TWINT' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right" :class="{ 'text-muted-foreground line-through': ticket.deleted_at }">
                            {{ formatMoney(ticket.total) }} CHF
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="text-muted-foreground text-sm">Aucun ticket.</p>
        </div>

        <DefineTicketContent v-slot="{ ticket: slotTicket }">
            <TicketDetail :ticket="slotTicket" :day-is-open="isOpen" @refunded="handleRefunded" />
        </DefineTicketContent>

        <Sheet v-if="isDesktop" :open="isSheetOpen" @update:open="isSheetOpen = $event">
            <SheetContent class="w-full sm:max-w-xl">
                <ScrollArea class="h-screen pb-4">
                    <SheetHeader>
                        <SheetTitle>Ticket #{{ selectedTicket?.id }}</SheetTitle>
                    </SheetHeader>
                    <div class="px-4 pt-4">
                        <ReuseTicketContent v-if="selectedTicket" :ticket="selectedTicket" />
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>

        <Drawer v-else :open="isSheetOpen" @update:open="isSheetOpen = $event">
            <DrawerContent>
                <DrawerHeader>
                    <DrawerTitle>Ticket #{{ selectedTicket?.id }}</DrawerTitle>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <ReuseTicketContent v-if="selectedTicket" :ticket="selectedTicket" />
                </div>
            </DrawerContent>
        </Drawer>
    </AppLayout>
</template>
