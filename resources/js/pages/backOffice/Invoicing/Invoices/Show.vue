<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, Invoice, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Pencil, Trash2, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    invoice: Invoice;
    balance: { paid_cents: number; credited_cents: number; outstanding_cents: number };
}>();

const page = usePage<SharedData>();
const user = page.props.auth.user;

const canCreate = computed(() => hasAnyPermission(user, ['create invoices', 'manage invoicing']));
const canIssue = computed(() => hasAnyPermission(user, ['issue invoices', 'manage invoicing']));
const canCancel = computed(() => hasAnyPermission(user, ['cancel invoices', 'manage invoicing']));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: props.invoice.invoice_number ?? `Brouillon #${props.invoice.id}`, href: route('bo.invoicing.invoices.show', props.invoice.id) },
];

const cancelDialogOpen = ref(false);
const cancelForm = useForm({ reason: '' });

const issueInvoice = () => {
    router.post(
        route('bo.invoicing.invoices.issue', props.invoice.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Facture émise.'),
            onError: (errors) => toast.error(errors.action ?? "Erreur lors de l'émission."),
        },
    );
};

const submitCancel = () => {
    cancelForm.post(route('bo.invoicing.invoices.cancel', props.invoice.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelDialogOpen.value = false;
            toast.success('Facture annulée.');
        },
        onError: (errors) => toast.error(errors.action ?? errors.reason ?? "Erreur lors de l'annulation."),
    });
};

const deleteInvoice = () => {
    router.delete(route('bo.invoicing.invoices.destroy', props.invoice.id), {
        onSuccess: () => toast.success('Facture brouillon supprimée.'),
        onError: (errors) => toast.error(errors.action ?? 'Erreur lors de la suppression.'),
    });
};

const statusLabel = computed(() => {
    return {
        draft: 'Brouillon',
        issued: 'Émise',
        partially_paid: 'Partiellement payée',
        paid: 'Payée',
        cancelled: 'Annulée',
    }[props.invoice.status];
});
</script>

<template>
    <Head :title="invoice.invoice_number ?? `Brouillon #${invoice.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-col gap-2">
                <H1>{{ invoice.invoice_number ?? `Brouillon #${invoice.id}` }}</H1>
                <div class="text-muted-foreground flex items-center gap-2 text-sm">
                    <Badge variant="secondary">{{ statusLabel }}</Badge>
                    <span>{{ invoice.recipient_name }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="invoice.status === 'draft' && canCreate" as-child variant="outline">
                    <Link :href="route('bo.invoicing.invoices.edit', invoice.id)"> <Pencil class="mr-2 h-4 w-4" /> Modifier </Link>
                </Button>
                <Button v-if="['issued', 'partially_paid', 'paid'].includes(invoice.status) && canCreate" as-child variant="outline">
                    <Link :href="route('bo.invoicing.credit-notes.create', { invoice_id: invoice.id })">Créer un avoir</Link>
                </Button>
                <AppAlertDialog
                    v-if="invoice.status === 'draft' && canIssue"
                    title="Émettre cette facture ?"
                    description="Un numéro définitif sera attribué. La facture ne pourra plus être modifiée."
                    cancel-text="Annuler"
                >
                    <template #trigger>
                        <Button> <CheckCircle2 class="mr-2 h-4 w-4" /> Émettre </Button>
                    </template>
                    <template #action>
                        <Button @click="issueInvoice">Émettre</Button>
                    </template>
                </AppAlertDialog>
                <Button v-if="invoice.status === 'issued' && canCancel" variant="destructive" @click="cancelDialogOpen = true">
                    <XCircle class="mr-2 h-4 w-4" /> Annuler
                </Button>
                <AppAlertDialog
                    v-if="invoice.status === 'draft' && canCreate"
                    title="Supprimer ce brouillon ?"
                    description="Le brouillon sera supprimé définitivement."
                    cancel-text="Annuler"
                >
                    <template #trigger>
                        <Button variant="destructive"> <Trash2 class="mr-2 h-4 w-4" /> Supprimer </Button>
                    </template>
                    <template #action>
                        <Button variant="destructive" @click="deleteInvoice">Supprimer</Button>
                    </template>
                </AppAlertDialog>
            </div>
        </div>

        <div class="mx-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4 lg:col-span-2">
                <H3>Destinataire</H3>
                <div class="mt-4 text-sm">
                    <div class="font-medium">{{ invoice.recipient_name }}</div>
                    <div>{{ invoice.recipient_address }}</div>
                    <div>{{ invoice.recipient_postal_code }} {{ invoice.recipient_city }}</div>
                    <div>{{ invoice.recipient_country }}</div>
                    <div v-if="invoice.recipient_email" class="text-muted-foreground">{{ invoice.recipient_email }}</div>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Dates</H3>
                <div class="mt-4 grid gap-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Émission</span>
                        <span>{{ invoice.issue_date ? formatDate(invoice.issue_date, 'DD.MM.YYYY') : '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Échéance</span>
                        <span>{{ invoice.due_date ? formatDate(invoice.due_date, 'DD.MM.YYYY') : '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Envoi</span>
                        <span>{{ invoice.delivery_method === 'email' ? 'Email' : 'Postal' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Situation financière</H3>
            <div class="mt-4 grid grid-cols-1 gap-2 text-sm md:grid-cols-4">
                <div>
                    <div class="text-muted-foreground">Total facture</div>
                    <div class="font-mono">{{ formatMoney(invoice.total) }}</div>
                </div>
                <div>
                    <div class="text-muted-foreground">Payé</div>
                    <div class="font-mono">{{ formatMoney(balance.paid_cents / 100) }}</div>
                </div>
                <div>
                    <div class="text-muted-foreground">Crédité par avoirs</div>
                    <div class="font-mono">{{ formatMoney(balance.credited_cents / 100) }}</div>
                </div>
                <div>
                    <div class="text-muted-foreground">Reste à payer</div>
                    <div class="font-mono font-semibold">{{ formatMoney(balance.outstanding_cents / 100) }}</div>
                </div>
            </div>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Lignes</H3>
            <div class="mt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Description</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead class="text-right">Quantité</TableHead>
                            <TableHead class="text-right">Prix unitaire</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in invoice.items" :key="item.id">
                            <TableCell>{{ item.description }}</TableCell>
                            <TableCell>{{ item.type_label ?? item.type }}</TableCell>
                            <TableCell class="text-right">{{ item.quantity }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatMoney(item.unit_price) }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatMoney(item.total) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="mt-4 flex flex-col items-end gap-1 text-sm">
                <div>
                    Sous-total: <span class="font-mono">{{ formatMoney(invoice.subtotal) }}</span>
                </div>
                <div class="text-base font-semibold">
                    Total: <span class="font-mono">{{ formatMoney(invoice.total) }}</span>
                </div>
            </div>
        </div>

        <div
            v-if="invoice.credit_note_applications?.length"
            class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4"
        >
            <H3>Crédits appliqués</H3>
            <Table class="mt-4">
                <TableHeader>
                    <TableRow>
                        <TableHead>Avoir</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead class="text-right">Montant</TableHead>
                        <TableHead class="text-right">Annulé</TableHead>
                        <TableHead class="text-right">Net</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="application in invoice.credit_note_applications" :key="application.id">
                        <TableCell class="font-mono">{{ application.credit_note?.credit_note_number ?? `#${application.credit_note_id}` }}</TableCell>
                        <TableCell>{{ formatDate(application.applied_at, 'DD.MM.YYYY') }}</TableCell>
                        <TableCell class="text-right font-mono">{{ formatMoney(application.amount) }}</TableCell>
                        <TableCell class="text-right font-mono">{{
                            formatMoney((application.reversals ?? []).reduce((sum, reversal) => sum + Number(reversal.amount), 0))
                        }}</TableCell>
                        <TableCell class="text-right font-mono">{{
                            formatMoney(
                                Number(application.amount) -
                                    (application.reversals ?? []).reduce((sum, reversal) => sum + Number(reversal.amount), 0),
                            )
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-if="invoice.cancellation_reason" class="border-destructive/40 mx-4 mt-4 rounded-xl border p-4 text-sm">
            <H3>Annulation</H3>
            <p class="mt-2">{{ invoice.cancellation_reason }}</p>
            <p v-if="invoice.cancelled_at" class="text-muted-foreground mt-1">{{ formatDate(invoice.cancelled_at, 'DD.MM.YYYY HH:mm') }}</p>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Historique</H3>
            <ul class="mt-4 flex flex-col gap-3 text-sm">
                <li v-for="log in invoice.activity_logs" :key="log.id" class="flex flex-col gap-1">
                    <div class="flex items-center justify-between gap-4">
                        <span class="font-medium">{{ log.action_label ?? log.action }}</span>
                        <span class="text-muted-foreground">{{ formatDate(log.created_at, 'DD.MM.YYYY HH:mm') }}</span>
                    </div>
                    <span v-if="log.user" class="text-muted-foreground">{{ log.user.name }}</span>
                </li>
            </ul>
        </div>

        <div class="h-4" aria-hidden="true"></div>

        <Dialog v-model:open="cancelDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Annuler la facture</DialogTitle>
                    <DialogDescription>Le numéro de facture est conservé. Un motif est obligatoire.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="reason">Motif</Label>
                    <Input id="reason" v-model="cancelForm.reason" type="text" placeholder="Motif de l'annulation" maxlength="1000" />
                    <p v-if="cancelForm.errors.reason" class="text-destructive text-sm">{{ cancelForm.errors.reason }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="cancelDialogOpen = false">Annuler</Button>
                    <Button variant="destructive" :disabled="cancelForm.processing" @click="submitCancel">Confirmer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
