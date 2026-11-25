<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, InvoicePaymentAllocation, Payment, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, RotateCcw, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    payment: Payment;
    allocatedCents: number;
    availableCents: number;
    allocatableInvoices: Array<{
        id: number;
        invoice_number: string | null;
        recipient_name: string;
        due_date: string | null;
        outstanding_cents: number;
    }>;
    allocationIdempotencyKey: string;
}>();

const page = usePage<SharedData>();
const canCreate = computed(() => hasAnyPermission(page.props.auth.user, ['create payments', 'manage invoicing']));
const canVoid = computed(() => canCreate.value && !props.payment.voided_at && props.allocatedCents === 0);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Paiements', href: route('bo.invoicing.payments.index') },
    { title: `Paiement #${props.payment.id}`, href: route('bo.invoicing.payments.show', props.payment.id) },
];

const allocationForm = useForm({
    invoice_id: '',
    amount: '',
    idempotency_key: props.allocationIdempotencyKey,
});

const selectedInvoice = computed(() => props.allocatableInvoices.find((invoice) => String(invoice.id) === String(allocationForm.invoice_id)));

const refreshDefaultAllocationAmount = (): void => {
    if (!selectedInvoice.value) {
        allocationForm.amount = '';
        return;
    }

    const cents = Math.min(props.availableCents, selectedInvoice.value.outstanding_cents);
    allocationForm.amount = (cents / 100).toFixed(2);
};

watch(() => allocationForm.invoice_id, refreshDefaultAllocationAmount);
watch(
    () => props.allocationIdempotencyKey,
    (key) => {
        allocationForm.idempotency_key = key;
    },
);

const allocate = (): void => {
    allocationForm.post(route('bo.invoicing.payments.allocations.store', props.payment.id), {
        preserveScroll: true,
        onSuccess: () => {
            allocationForm.invoice_id = '';
            allocationForm.amount = '';
        },
        onError: (errors) => toast.error(errors.action ?? errors.amount ?? "L'imputation a échoué."),
    });
};

const voidDialogOpen = ref(false);
const voidForm = useForm({ reason: '' });

const openVoidDialog = (): void => {
    voidForm.reset();
    voidDialogOpen.value = true;
};

const submitVoid = (): void => {
    voidForm.post(route('bo.invoicing.payments.void', props.payment.id), {
        preserveScroll: true,
        onSuccess: () => {
            voidDialogOpen.value = false;
        },
        onError: (errors) => toast.error(errors.action ?? errors.reason ?? "L'annulation a échoué."),
    });
};

const reverseDialogOpen = ref(false);
const reverseTarget = ref<InvoicePaymentAllocation | null>(null);
const reverseForm = useForm({ reason: '' });

const openReverseDialog = (allocation: InvoicePaymentAllocation): void => {
    reverseTarget.value = allocation;
    reverseForm.reset();
    reverseDialogOpen.value = true;
};

const submitReverse = (): void => {
    if (!reverseTarget.value) {
        return;
    }

    reverseForm.post(route('bo.invoicing.payments.allocations.reverse', reverseTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            reverseDialogOpen.value = false;
            reverseTarget.value = null;
        },
        onError: (errors) => toast.error(errors.action ?? errors.reason ?? "L'annulation de l'imputation a échoué."),
    });
};

const allocationStatusLabel = (allocation: InvoicePaymentAllocation): string => (allocation.reversed_at ? 'Annulée' : 'Active');

const allocationActionError = computed(() => (allocationForm.errors as Record<string, string>).action);
const voidActionError = computed(() => (voidForm.errors as Record<string, string>).action);
const reverseActionError = computed(() => (reverseForm.errors as Record<string, string>).action);

const goToEdit = (): void => {
    router.visit(route('bo.invoicing.payments.edit', props.payment.id));
};
</script>

<template>
    <Head :title="`Paiement #${payment.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Paiement #{{ payment.id }}</H1>
                <div class="text-muted-foreground mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <Badge :variant="payment.voided_at ? 'destructive' : 'secondary'">{{ payment.voided_at ? 'Annulé' : 'Actif' }}</Badge>
                    <span>{{ formatDate(payment.payment_date, 'DD.MM.YYYY') }}</span>
                    <span>·</span>
                    <span>{{ payment.method_label }}</span>
                </div>
            </div>
            <div v-if="canCreate" class="flex flex-wrap gap-2">
                <Button v-if="!payment.voided_at" variant="outline" @click="goToEdit"> <Pencil class="mr-2 h-4 w-4" /> Modifier </Button>
                <Button v-if="canVoid" variant="destructive" @click="openVoidDialog"> <Trash2 class="mr-2 h-4 w-4" /> Annuler le paiement </Button>
            </div>
        </div>

        <div v-if="payment.voided_at" class="border-destructive/40 bg-destructive/5 mx-4 rounded-xl border p-4 text-sm">
            <p class="font-medium">Paiement annulé</p>
            <p class="mt-1">{{ payment.void_reason }}</p>
            <p class="text-muted-foreground mt-1">
                {{ payment.voider?.name ?? 'Utilisateur inconnu' }} · {{ formatDate(payment.voided_at, 'DD.MM.YYYY HH:mm') }}
            </p>
        </div>

        <div class="mx-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4 lg:col-span-2">
                <H3>Informations</H3>
                <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <div class="text-muted-foreground">Montant</div>
                        <div class="font-mono text-lg font-semibold">{{ formatMoney(payment.amount) }} CHF</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Enregistré par</div>
                        <div>{{ payment.creator?.name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Référence</div>
                        <div>{{ payment.reference || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Notes</div>
                        <div class="whitespace-pre-line">{{ payment.notes || '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Solde du paiement</H3>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Alloué</span>
                        <span class="font-mono">{{ formatMoney(allocatedCents / 100) }} CHF</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Non alloué</span>
                        <span class="font-mono font-semibold">{{ formatMoney(availableCents / 100) }} CHF</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Imputations</H3>
            <p v-if="!payment.allocations?.length" class="text-muted-foreground mt-4 text-sm">Aucune imputation enregistrée.</p>
            <div v-else class="mt-4 overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Facture</TableHead>
                            <TableHead>Client</TableHead>
                            <TableHead class="text-right">Montant</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead>Détail</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="allocation in payment.allocations" :key="allocation.id">
                            <TableCell>{{ formatDate(allocation.created_at, 'DD.MM.YYYY HH:mm') }}</TableCell>
                            <TableCell>
                                <Link
                                    v-if="allocation.invoice"
                                    :href="route('bo.invoicing.invoices.show', allocation.invoice.id)"
                                    class="text-primary font-mono hover:underline"
                                >
                                    {{ allocation.invoice.invoice_number }}
                                </Link>
                            </TableCell>
                            <TableCell>{{ allocation.invoice?.recipient_name ?? '—' }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatMoney(allocation.amount) }}</TableCell>
                            <TableCell>
                                <Badge :variant="allocation.reversed_at ? 'destructive' : 'secondary'">{{ allocationStatusLabel(allocation) }}</Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground max-w-sm whitespace-normal">
                                <span v-if="allocation.reversed_at"> {{ allocation.reversal_reason }} · {{ allocation.reverser?.name ?? '—' }} </span>
                                <span v-else>—</span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    v-if="canCreate && !allocation.reversed_at"
                                    size="sm"
                                    variant="outline"
                                    @click="openReverseDialog(allocation)"
                                >
                                    <RotateCcw class="mr-2 h-4 w-4" /> Annuler
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <div
            v-if="canCreate && !payment.voided_at && availableCents > 0 && allocatableInvoices.length"
            class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4"
        >
            <H3>Allouer le solde</H3>
            <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="allocate">
                <div class="grid gap-2">
                    <Label for="invoice_id">Facture</Label>
                    <Select v-model="allocationForm.invoice_id">
                        <SelectTrigger id="invoice_id" class="w-full"><SelectValue placeholder="Sélectionner une facture" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="invoice in allocatableInvoices" :key="invoice.id" :value="String(invoice.id)">
                                {{ invoice.invoice_number }} · {{ invoice.recipient_name }} · reste
                                {{ formatMoney(invoice.outstanding_cents / 100) }} CHF
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="allocationForm.errors.invoice_id" class="text-destructive text-sm">{{ allocationForm.errors.invoice_id }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="allocation_amount">Montant</Label>
                    <Input id="allocation_amount" v-model="allocationForm.amount" type="number" min="0.01" step="0.01" />
                    <p v-if="allocationForm.errors.amount" class="text-destructive text-sm">{{ allocationForm.errors.amount }}</p>
                    <p v-if="allocationActionError" class="text-destructive text-sm">{{ allocationActionError }}</p>
                </div>

                <div class="md:col-span-2">
                    <Button type="submit" :disabled="allocationForm.processing || !allocationForm.invoice_id">Imputer le paiement</Button>
                </div>
            </form>
        </div>

        <div v-else-if="canCreate && !payment.voided_at && availableCents > 0" class="text-muted-foreground mx-4 mt-4 text-sm">
            Aucune facture avec un solde restant à payer.
        </div>

        <div v-if="canCreate && !payment.voided_at && allocatedCents > 0" class="text-muted-foreground mx-4 mt-4 text-sm">
            Retirez les imputations actives avant d’annuler ce paiement.
        </div>

        <Dialog v-model:open="voidDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Annuler le paiement</DialogTitle>
                    <DialogDescription>Le paiement restera dans l’historique. Un motif est obligatoire.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="void_reason">Motif</Label>
                    <Input id="void_reason" v-model="voidForm.reason" maxlength="1000" />
                    <p v-if="voidForm.errors.reason" class="text-destructive text-sm">{{ voidForm.errors.reason }}</p>
                    <p v-if="voidActionError" class="text-destructive text-sm">{{ voidActionError }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="voidDialogOpen = false">Annuler</Button>
                    <Button variant="destructive" :disabled="voidForm.processing" @click="submitVoid">Confirmer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="reverseDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Annuler l’imputation</DialogTitle>
                    <DialogDescription>Le paiement redeviendra disponible. Un motif est obligatoire.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="reverse_reason">Motif</Label>
                    <Input id="reverse_reason" v-model="reverseForm.reason" maxlength="1000" />
                    <p v-if="reverseForm.errors.reason" class="text-destructive text-sm">{{ reverseForm.errors.reason }}</p>
                    <p v-if="reverseActionError" class="text-destructive text-sm">{{ reverseActionError }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="reverseDialogOpen = false">Annuler</Button>
                    <Button variant="destructive" :disabled="reverseForm.processing" @click="submitReverse">Confirmer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
