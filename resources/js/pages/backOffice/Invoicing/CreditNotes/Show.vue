<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, CreditNote, CreditNoteApplication, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Info, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    creditNote: CreditNote;
    availableCents: number;
    appliedCents: number;
    refundedCents: number;
    reversedCents: number;
    targetInvoices: Array<{ id: number; invoice_number: string | null; recipient_name: string; total: string; status: string }>;
}>();

const page = usePage<SharedData>();
const user = page.props.auth.user;
const canCreate = computed(() => hasAnyPermission(user, ['create invoices', 'manage invoicing']));
const canIssue = computed(() => hasAnyPermission(user, ['issue invoices', 'manage invoicing']));
const canManage = computed(() => hasAnyPermission(user, ['manage invoicing']));
const reversible = computed(() => props.availableCents > 0 && props.appliedCents === 0);
const reversalBlockedReason = computed(() => {
    if (props.appliedCents > 0) {
        return "Annulez d'abord les imputations.";
    }

    return 'Aucun crédit disponible à annuler.';
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avoirs', href: route('bo.invoicing.credit-notes.index') },
    {
        title: props.creditNote.credit_note_number ?? `Brouillon #${props.creditNote.id}`,
        href: route('bo.invoicing.credit-notes.show', props.creditNote.id),
    },
];

const applyDialogOpen = ref(false);
const applyForm = useForm({ invoice_id: '', amount: '' });
const refundDialogOpen = ref(false);
const refundForm = useForm({
    amount: '',
    refund_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
});
const reverseDialogOpen = ref(false);
const reverseTarget = ref<CreditNoteApplication | null>(null);
const reverseForm = useForm({ amount: '', reason: '' });

const issueCreditNote = () => {
    router.post(
        route('bo.invoicing.credit-notes.issue', props.creditNote.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Avoir émis.'),
            onError: (errors) => toast.error(errors.action ?? "Erreur lors de l'émission de l'avoir."),
        },
    );
};

const deleteCreditNote = () => {
    router.delete(route('bo.invoicing.credit-notes.destroy', props.creditNote.id), {
        onSuccess: () => toast.success('Avoir brouillon supprimé.'),
        onError: (errors) => toast.error(errors.action ?? "Erreur lors de la suppression de l'avoir."),
    });
};

const submitApply = () => {
    applyForm.post(route('bo.invoicing.credit-notes.apply', props.creditNote.id), {
        preserveScroll: true,
        onSuccess: () => {
            applyDialogOpen.value = false;
            applyForm.reset();
            toast.success('Avoir imputé.');
        },
        onError: (errors) => toast.error(errors.amount ?? errors.action ?? "Erreur lors de l'imputation."),
    });
};

const submitRefund = () => {
    refundForm.post(route('bo.invoicing.credit-notes.refund', props.creditNote.id), {
        preserveScroll: true,
        onSuccess: () => {
            refundDialogOpen.value = false;
            refundForm.reset();
            toast.success('Remboursement enregistré.');
        },
        onError: (errors) => toast.error(errors.amount ?? errors.action ?? "Erreur lors de l'enregistrement du remboursement."),
    });
};

const openReverseDialog = (application: CreditNoteApplication) => {
    reverseTarget.value = application;
    reverseForm.reset();
    reverseForm.amount = String(applicationNet(application));
    reverseDialogOpen.value = true;
};

const submitReverseApplication = () => {
    if (!reverseTarget.value) {
        return;
    }

    reverseForm.post(route('bo.invoicing.credit-note-applications.reverse', reverseTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            reverseDialogOpen.value = false;
            toast.success('Imputation annulée.');
        },
        onError: (errors) => toast.error(errors.amount ?? errors.action ?? "Erreur lors de l'annulation de l'imputation."),
    });
};

const issueReversal = (reversalId: number) => {
    router.post(
        route('bo.invoicing.credit-note-reversals.issue', reversalId),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success("Avoir d'annulation émis."),
            onError: (errors) => toast.error(errors.action ?? "Erreur lors de l'émission de l'avoir d'annulation."),
        },
    );
};

const applicationNet = (application: CreditNoteApplication): number => {
    const reversed = (application.reversals ?? []).reduce((total, reversal) => total + Number(reversal.amount), 0);

    return Math.max(0, Number(application.amount) - reversed);
};

const statusLabel = computed(() => {
    return {
        draft: 'Brouillon',
        issued: 'Émis',
        partially_used: 'Partiellement utilisé',
        fully_used: 'Entièrement utilisé',
        partially_reversed: 'Partiellement annulé',
        reversed: 'Annulé',
    }[props.creditNote.status];
});
</script>

<template>
    <Head :title="creditNote.credit_note_number ?? `Brouillon #${creditNote.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-col gap-2">
                <H1>{{ creditNote.credit_note_number ?? `Brouillon #${creditNote.id}` }}</H1>
                <div class="text-muted-foreground flex items-center gap-2 text-sm">
                    <Badge variant="secondary">{{ statusLabel }}</Badge>
                    <Link class="underline" :href="route('bo.invoicing.invoices.show', creditNote.invoice_id)">
                        Facture {{ creditNote.invoice?.invoice_number }}
                    </Link>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="creditNote.status === 'draft' && canCreate" as-child variant="outline">
                    <Link :href="route('bo.invoicing.credit-notes.edit', creditNote.id)"> <Pencil class="mr-2 h-4 w-4" /> Modifier </Link>
                </Button>
                <AppAlertDialog
                    v-if="creditNote.status === 'draft' && canIssue"
                    title="Émettre cet avoir ?"
                    description="Un numéro définitif sera attribué. L'avoir ne pourra plus être modifié."
                    cancel-text="Annuler"
                >
                    <template #trigger>
                        <Button><CheckCircle2 class="mr-2 h-4 w-4" /> Émettre</Button>
                    </template>
                    <template #action>
                        <Button @click="issueCreditNote">Émettre</Button>
                    </template>
                </AppAlertDialog>
                <Button v-if="creditNote.status !== 'draft' && availableCents > 0 && canManage" variant="outline" @click="applyDialogOpen = true">
                    Imputer à une facture
                </Button>
                <Button v-if="creditNote.status !== 'draft' && availableCents > 0 && canManage" variant="outline" @click="refundDialogOpen = true">
                    Remboursement
                </Button>
                <Button v-if="creditNote.status !== 'draft' && canManage && reversible" as-child variant="outline">
                    <Link :href="route('bo.invoicing.credit-notes.reversals.create', creditNote.id)">Créer un avoir d'annulation</Link>
                </Button>
                <Tooltip v-else-if="creditNote.status !== 'draft' && canManage">
                    <TooltipTrigger as-child>
                        <span>
                            <Button disabled variant="outline"> <Info class="mr-2 h-4 w-4" /> Créer un avoir d'annulation </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>{{ reversalBlockedReason }}</TooltipContent>
                </Tooltip>
                <AppAlertDialog
                    v-if="creditNote.status === 'draft' && canCreate"
                    title="Supprimer ce brouillon ?"
                    description="L'avoir brouillon sera supprimé définitivement."
                    cancel-text="Annuler"
                >
                    <template #trigger>
                        <Button variant="destructive"><Trash2 class="mr-2 h-4 w-4" /> Supprimer</Button>
                    </template>
                    <template #action>
                        <Button variant="destructive" @click="deleteCreditNote">Supprimer</Button>
                    </template>
                </AppAlertDialog>
            </div>
        </div>

        <div class="mx-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4 lg:col-span-2">
                <H3>Client</H3>
                <div class="mt-4 text-sm">
                    <div class="font-medium">{{ creditNote.recipient_name }}</div>
                    <div>{{ creditNote.recipient_address }}</div>
                    <div>{{ creditNote.recipient_postal_code }} {{ creditNote.recipient_city }}</div>
                    <div>{{ creditNote.recipient_country }}</div>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Montants</H3>
                <div class="mt-4 grid gap-2 text-sm">
                    <div class="flex justify-between">
                        <span class="inline-flex items-center gap-1">
                            Total
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Info class="text-muted-foreground h-3.5 w-3.5" />
                                </TooltipTrigger>
                                <TooltipContent>Montant total du crédit accordé par cet avoir.</TooltipContent>
                            </Tooltip>
                        </span>
                        <span class="font-mono">{{ formatMoney(creditNote.total) }}</span>
                    </div>
                    <div v-if="appliedCents > 0" class="flex justify-between">
                        <span class="inline-flex items-center gap-1">
                            Imputé
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Info class="text-muted-foreground h-3.5 w-3.5" />
                                </TooltipTrigger>
                                <TooltipContent>Montant du crédit déjà utilisé pour réduire une facture.</TooltipContent>
                            </Tooltip>
                        </span>
                        <span class="font-mono">{{ formatMoney(appliedCents / 100) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="inline-flex items-center gap-1">
                            Remboursé
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Info class="text-muted-foreground h-3.5 w-3.5" />
                                </TooltipTrigger>
                                <TooltipContent>Montant déjà remboursé au client. Il réduit le crédit disponible.</TooltipContent>
                            </Tooltip>
                        </span>
                        <span class="font-mono">{{ formatMoney(refundedCents / 100) }}</span>
                    </div>
                    <div v-if="reversedCents > 0" class="flex justify-between">
                        <span class="inline-flex items-center gap-1">
                            Annulé
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Info class="text-muted-foreground h-3.5 w-3.5" />
                                </TooltipTrigger>
                                <TooltipContent>Montant déjà annulé par un ou plusieurs avoirs d'annulation.</TooltipContent>
                            </Tooltip>
                        </span>
                        <span class="font-mono">{{ formatMoney(reversedCents / 100) }}</span>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <span class="inline-flex items-center gap-1">
                            Disponible
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Info class="text-muted-foreground h-3.5 w-3.5" />
                                </TooltipTrigger>
                                <TooltipContent
                                    >Crédit restant utilisable pour une imputation, un remboursement ou un avoir d'annulation.</TooltipContent
                                >
                            </Tooltip>
                        </span>
                        <span class="font-mono">{{ formatMoney(availableCents / 100) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Émis le</span><span>{{ creditNote.issued_at ? formatDate(creditNote.issued_at, 'DD.MM.YYYY') : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Motif</H3>
            <p class="mt-2 text-sm">{{ creditNote.reason }}</p>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Lignes</H3>
            <div class="mt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Quantité</TableHead>
                            <TableHead class="text-right">Prix unitaire</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in creditNote.items" :key="item.id">
                            <TableCell>{{ item.description }}</TableCell>
                            <TableCell class="text-right">{{ item.quantity }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatMoney(item.unit_price) }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatMoney(item.total) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <div v-if="creditNote.applications?.length" class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Imputations</H3>
            <Table class="mt-4">
                <TableHeader>
                    <TableRow>
                        <TableHead>Facture</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead class="text-right">Montant initial</TableHead>
                        <TableHead class="text-right">Annulé</TableHead>
                        <TableHead class="text-right">Net</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="application in creditNote.applications" :key="application.id">
                        <TableCell>
                            <Link class="underline" :href="route('bo.invoicing.invoices.show', application.invoice_id)">
                                {{ application.invoice?.invoice_number ?? `#${application.invoice_id}` }}
                            </Link>
                        </TableCell>
                        <TableCell>{{ formatDate(application.applied_at, 'DD.MM.YYYY') }}</TableCell>
                        <TableCell class="text-right font-mono">{{ formatMoney(application.amount) }}</TableCell>
                        <TableCell
                            class="text-right font-mono"
                            :title="
                                (application.reversals ?? [])
                                    .map((reversal) => `${formatDate(reversal.reversed_at, 'DD.MM.YYYY')} · ${reversal.reason}`)
                                    .join('\n')
                            "
                            >{{ formatMoney((application.reversals ?? []).reduce((sum, reversal) => sum + Number(reversal.amount), 0)) }}</TableCell
                        >
                        <TableCell class="text-right font-mono">
                            <span :class="{ 'text-muted-foreground line-through': applicationNet(application) === 0 }">{{
                                formatMoney(applicationNet(application))
                            }}</span>
                            <Badge v-if="applicationNet(application) === 0" variant="outline" class="ml-2">Annulé</Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button
                                v-if="canManage && applicationNet(application) > 0"
                                size="sm"
                                variant="outline"
                                @click="openReverseDialog(application)"
                            >
                                Annuler l'imputation
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-if="creditNote.refunds?.length" class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Remboursements</H3>
            <Table class="mt-4">
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Méthode</TableHead>
                        <TableHead class="text-right">Montant</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="refund in creditNote.refunds" :key="refund.id">
                        <TableCell>{{ formatDate(refund.refund_date, 'DD.MM.YYYY') }}</TableCell>
                        <TableCell>{{ refund.payment_method_label ?? refund.payment_method }}</TableCell>
                        <TableCell class="text-right font-mono">{{ formatMoney(refund.amount) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-if="creditNote.reversals?.length" class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Avoirs d'annulation</H3>
            <Table class="mt-4">
                <TableHeader>
                    <TableRow>
                        <TableHead>Numéro</TableHead>
                        <TableHead>Statut</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="reversal in creditNote.reversals" :key="reversal.id">
                        <TableCell class="font-mono">{{ reversal.credit_note_reversal_number ?? 'Brouillon' }}</TableCell>
                        <TableCell>{{ reversal.status === 'issued' ? 'Émis' : 'Brouillon' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ formatMoney(reversal.total) }}</TableCell>
                        <TableCell class="text-right">
                            <Button v-if="reversal.status === 'draft' && canManage" size="sm" @click="issueReversal(reversal.id)">Émettre</Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Dialog v-model:open="applyDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Imputer l'avoir</DialogTitle>
                    <DialogDescription>La facture cible doit appartenir au même client.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="target_invoice">Facture</Label>
                    <Select v-model="applyForm.invoice_id">
                        <SelectTrigger id="target_invoice" class="w-full">
                            <SelectValue placeholder="Sélectionner une facture" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="invoice in targetInvoices" :key="invoice.id" :value="String(invoice.id)">
                                {{ invoice.invoice_number }} · {{ invoice.recipient_name }} · {{ formatMoney(invoice.total) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Label for="apply_amount">Montant</Label>
                    <Input id="apply_amount" v-model="applyForm.amount" type="number" step="0.01" min="0.01" />
                    <p v-if="applyForm.errors.amount" class="text-destructive text-sm">{{ applyForm.errors.amount }}</p>
                    <p v-if="applyForm.errors.invoice_id" class="text-destructive text-sm">{{ applyForm.errors.invoice_id }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="applyDialogOpen = false">Annuler</Button>
                    <Button :disabled="applyForm.processing" @click="submitApply">Imputer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="refundDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Enregistrer un remboursement</DialogTitle>
                    <DialogDescription>Le remboursement réduit le crédit disponible.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="refund_amount">Montant</Label>
                    <Input id="refund_amount" v-model="refundForm.amount" type="number" step="0.01" min="0.01" :max="availableCents / 100" />
                    <p class="text-muted-foreground text-xs">Disponible : {{ formatMoney(availableCents / 100) }}</p>
                    <p v-if="refundForm.errors.amount" class="text-destructive text-sm">{{ refundForm.errors.amount }}</p>
                    <Label for="refund_date">Date</Label>
                    <Input id="refund_date" v-model="refundForm.refund_date" type="date" />
                    <Label for="refund_method">Méthode</Label>
                    <Select v-model="refundForm.payment_method">
                        <SelectTrigger id="refund_method" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="bank_transfer">Virement bancaire</SelectItem>
                            <SelectItem value="cash">Espèces</SelectItem>
                            <SelectItem value="card">Carte</SelectItem>
                            <SelectItem value="other">Autre</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="refundDialogOpen = false">Annuler</Button>
                    <Button :disabled="refundForm.processing" @click="submitRefund">Enregistrer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="reverseDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Annuler l'imputation</DialogTitle>
                    <DialogDescription>L'imputation sera annulée par une opération auditée.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="reverse_amount">Montant</Label>
                    <Input id="reverse_amount" v-model="reverseForm.amount" type="number" step="0.01" min="0.01" />
                    <Label for="reverse_reason">Motif</Label>
                    <Input id="reverse_reason" v-model="reverseForm.reason" type="text" />
                    <p v-if="reverseForm.errors.amount" class="text-destructive text-sm">{{ reverseForm.errors.amount }}</p>
                    <p v-if="reverseForm.errors.reason" class="text-destructive text-sm">{{ reverseForm.errors.reason }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="reverseDialogOpen = false">Annuler</Button>
                    <Button variant="destructive" :disabled="reverseForm.processing" @click="submitReverseApplication">Annuler l'imputation</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
