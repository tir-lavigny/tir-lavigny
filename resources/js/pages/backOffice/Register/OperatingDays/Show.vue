<script setup lang="ts">
import TicketDetail from '@/components/back-office/Register/TicketDetail.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasPermissionTo, parseDateOnly } from '@/lib/utils';
import type { BreadcrumbItem, CashMovement, OperatingDay, Product, SharedData, Ticket } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { createReusableTemplate, useMediaQuery } from '@vueuse/core';
import { Info, Lock, Plus, Unlock } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    operatingDay: OperatingDay;
    correctionProduct: Product | null;
    openingBalance: number;
    cashMovements: CashMovement[];
    laterClosedDaysCount: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journées', href: route('bo.register.operating-days.index') },
    { title: formatDate(props.operatingDay.date, 'DD.MM.YYYY'), href: route('bo.register.operating-days.show', props.operatingDay.id) },
];

const closeForm = useForm({ status: 'closed' });
const reopenForm = useForm({ status: 'open' });

const isOpen = computed(() => !props.operatingDay.closed_at);

const dayMovementsTotal = computed(() => props.cashMovements.reduce((total, movement) => total + Number(movement.amount), 0));

const isDesktop = useMediaQuery('(min-width: 768px)');
const selectedTicket = ref<Ticket | null>(null);
const isSheetOpen = ref(false);

const ordredTickets = computed(() => {
    return props.operatingDay.tickets?.slice().sort((a, b) => new Date(b.sold_at).getTime() - new Date(a.sold_at).getTime()) || [];
});

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
    router.reload({ only: ['operatingDay', 'openingBalance', 'cashMovements'] });
}

const correctionForm = useForm({ amount: '' as string | number });
const isCorrectionDialogOpen = ref(false);

const canCreateCorrection = computed(() => props.correctionProduct !== null && hasPermissionTo(user, 'create ticket'));

function openCorrectionDialog() {
    correctionForm.reset();
    correctionForm.clearErrors();
    isCorrectionDialogOpen.value = true;
}

function handleCorrection() {
    correctionForm.post(route('bo.register.operating-days.corrections.store', props.operatingDay.id), {
        preserveScroll: true,
        onSuccess: () => {
            isCorrectionDialogOpen.value = false;
            toast.success('Ticket correctif enregistré.');
        },
        onError: () => toast.error('Erreur lors de la création du ticket correctif.'),
    });
}

function isCorrection(ticket: Ticket) {
    return props.correctionProduct !== null && ticket.ticket_items?.some((item) => item.product_id === props.correctionProduct?.id);
}

onMounted(() => {
    try {
        const url = new URL(page.props.ziggy.url + page.url);
        const ticketId = url.searchParams.get('ticket');
        if (!ticketId) return;
        const ticket = props.operatingDay.tickets?.find((t) => t.id === Number(ticketId));
        if (!ticket) return;
        openTicket(ticket);
        url.searchParams.delete('ticket');
        window.history.replaceState({}, '', url.toString());
    } catch {
        console.error('Failed to parse ticket from URL');
        toast.error('Erreur lors de la lecture du ticket.');
    }
});
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
                <Button v-if="canCreateCorrection" variant="outline" @click="openCorrectionDialog">
                    <Plus class="mr-2 size-4" /> Ticket correctif
                </Button>
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
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Solde d'ouverture</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ formatMoney(openingBalance) }} CHF</p></CardContent
                    >
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Ventes espèces</CardTitle></CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ formatMoney(operatingDay.total_cash) }} CHF</p></CardContent
                    >
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center gap-1 pb-2">
                        <CardTitle class="text-sm">Ventes Twint</CardTitle>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Info class="text-muted-foreground size-3.5 cursor-help" aria-label="Aide sur les ventes Twint" />
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs">
                                <p>
                                    Les ventes Twint sont enregistrées pour les statistiques, mais n'entrent pas dans le comptage final de la caisse.
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </CardHeader>
                    <CardContent
                        ><p class="text-2xl font-bold">{{ formatMoney(operatingDay.total_twint) }} CHF</p></CardContent
                    >
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center gap-1 pb-2">
                        <CardTitle class="text-sm">Mouvements de caisse</CardTitle>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Info
                                    class="text-muted-foreground size-3.5 cursor-help"
                                    aria-label="Aide sur les mouvements de caisse de la journée"
                                />
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs">
                                <p>Transferts d'argent entre la caisse et la banque (retraits et injections) pris en compte pour cette journée.</p>
                            </TooltipContent>
                        </Tooltip>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold" :class="dayMovementsTotal < 0 ? 'text-destructive' : ''">
                            {{ formatMoney(dayMovementsTotal) }} CHF
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Solde attendu de clôture</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ formatMoney(operatingDay.closing_balance) }} CHF</p>
                    </CardContent>
                </Card>
            </div>

            <div v-if="cashMovements.length" class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Mouvements de caisse</H3>
                <ul class="mt-4 flex flex-col gap-2 text-sm">
                    <li v-for="movement in cashMovements" :key="movement.id" class="flex items-center justify-between">
                        <span>
                            {{ formatDate(parseDateOnly(movement.occurred_at), 'DD.MM.YYYY') }} —
                            {{ Number(movement.amount) < 0 ? 'Retrait vers la banque' : 'Injection depuis la banque' }}
                            <span v-if="movement.remark" class="text-muted-foreground">({{ movement.remark }})</span>
                        </span>
                        <span :class="Number(movement.amount) < 0 ? 'text-destructive' : ''">{{ formatMoney(movement.amount) }} CHF</span>
                    </li>
                </ul>
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
                        <TableHead>Lieu</TableHead>
                        <TableHead>Paiement</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="ticket in ordredTickets"
                        :key="ticket.id"
                        class="hover:bg-muted/50 cursor-pointer transition-colors"
                        :class="{ 'opacity-50': ticket.deleted_at }"
                        @click="openTicket(ticket)"
                    >
                        <TableCell class="font-medium">
                            {{ ticket.id }}
                            <Badge v-if="isCorrection(ticket)" variant="outline" class="ml-2">Correction</Badge>
                            <Badge v-if="ticket.deleted_at" variant="destructive" class="ml-2">Remboursé</Badge>
                        </TableCell>
                        <TableCell>{{ formatDate(ticket.sold_at, 'HH:mm') }}</TableCell>
                        <TableCell>
                            {{ ticket.stock_location?.name ?? 'N/A' }}
                        </TableCell>
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

        <Dialog :open="isCorrectionDialogOpen" @update:open="isCorrectionDialogOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ticket correctif</DialogTitle>
                    <DialogDescription>
                        Ajoute un ajustement d'espèces à cette journée, même si elle est fermée. Un montant négatif retire de l'argent de la caisse.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="laterClosedDaysCount > 0" class="rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm">
                    {{ laterClosedDaysCount }} journée(s) postérieure(s) sont déjà fermées. Leur solde de clôture sera recalculé en cascade.
                </div>
                <div class="grid gap-2">
                    <Label for="correction-amount">Montant (CHF)</Label>
                    <Input id="correction-amount" v-model="correctionForm.amount" type="number" step="0.01" placeholder="Ex: -15.00" />
                    <p v-if="correctionForm.errors.amount" class="text-destructive text-sm">{{ correctionForm.errors.amount }}</p>
                    <p class="text-muted-foreground text-xs">Paiement en espèces uniquement.</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" :disabled="correctionForm.processing" @click="isCorrectionDialogOpen = false">Annuler</Button>
                    <Button :disabled="correctionForm.processing" @click="handleCorrection">
                        {{ laterClosedDaysCount > 0 ? 'Confirmer et recalculer' : 'Enregistrer' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
