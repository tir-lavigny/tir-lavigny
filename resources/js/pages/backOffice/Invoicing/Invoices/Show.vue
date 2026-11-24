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
import { useInvoicePrinting } from '@/composables/useInvoicePrinting';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Invoice, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, CheckCircle2, Download, Mail, Pencil, Printer, RefreshCw, Trash2, XCircle } from '@lucide/vue';
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
const canManageInvoicing = computed(() => hasPermissionTo(user, 'manage invoicing'));
const canSend = computed(() => hasAnyPermission(user, ['send invoices', 'manage invoicing']));
const hasStoredDocument = computed(() => props.invoice.document?.status === 'stored');
const documentFailed = computed(() => props.invoice.document?.status === 'failed');
const isDistributable = computed(() => ['issued', 'partially_paid', 'paid'].includes(props.invoice.status));
const emailPending = computed(() =>
    (props.invoice.deliveries ?? []).some((delivery) => delivery.channel === 'email' && delivery.status === 'pending'),
);
const emailSent = computed(() => (props.invoice.deliveries ?? []).some((delivery) => delivery.channel === 'email' && delivery.status === 'sent'));
const deliveryStarted = computed(() => (props.invoice.deliveries ?? []).some((delivery) => ['pending', 'sent', 'printed'].includes(delivery.status)));
const canSendEmail = computed(
    () => isDistributable.value && canSend.value && props.invoice.delivery_method === 'email' && hasStoredDocument.value && !emailPending.value,
);
const canPrintInvoice = computed(
    () => isDistributable.value && canSend.value && props.invoice.delivery_method === 'postal' && hasStoredDocument.value,
);
const canChangeDeliveryMethod = computed(() => isDistributable.value && canSend.value && !deliveryStarted.value);
const { printing, printInvoice: printInvoicePdf } = useInvoicePrinting();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: props.invoice.invoice_number ?? `Brouillon #${props.invoice.id}`, href: route('bo.invoicing.invoices.show', props.invoice.id) },
];

const cancelDialogOpen = ref(false);
const cancelForm = useForm({ reason: '' });
const deliveryDialogOpen = ref(false);
const deliveryMethodForm = useForm({ delivery_method: props.invoice.delivery_method });

const issueInvoice = () => {
    router.post(
        route('bo.invoicing.invoices.issue', props.invoice.id),
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const issued = page.props.invoice as Invoice | undefined;
                if (issued?.document?.status === 'failed') {
                    toast.warning('Facture émise, mais le PDF n’a pas pu être archivé.');
                } else {
                    toast.success('Facture émise.');
                }
            },
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

const retryDocument = () => {
    if (!props.invoice.document) {
        return;
    }

    router.post(
        route('bo.invoicing.financial-documents.retry', props.invoice.document.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('PDF archivé.'),
            onError: (errors) => toast.error(errors.document ?? "Erreur lors de l'archivage du PDF."),
        },
    );
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

const deliveryStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        pending: 'En attente',
        sent: 'Envoyé',
        failed: 'Échec',
        printed: 'Imprimé',
    };

    return labels[status] ?? status;
};

const channelLabel = (channel: string): string => (channel === 'email' ? 'Email' : 'Postal');

const sendEmail = (): void => {
    router.post(
        route('bo.invoicing.invoices.email', props.invoice.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) => toast.error(errors.action ?? "L'envoi a échoué."),
        },
    );
};

const printCurrentInvoice = async (): Promise<void> => {
    await printInvoicePdf(props.invoice.id);
};

const openDeliveryDialog = (): void => {
    deliveryMethodForm.delivery_method = props.invoice.delivery_method;
    deliveryMethodForm.clearErrors();
    deliveryDialogOpen.value = true;
};

const submitDeliveryMethod = (): void => {
    deliveryMethodForm.put(route('bo.invoicing.invoices.delivery-method', props.invoice.id), {
        preserveScroll: true,
        onSuccess: () => {
            deliveryDialogOpen.value = false;
        },
        onError: (errors) => {
            const formErrors = errors as Record<string, string>;
            toast.error(formErrors.action ?? formErrors.delivery_method ?? 'Le mode de distribution n’a pas pu être modifié.');
        },
    });
};
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
                <Button v-if="hasStoredDocument" as-child variant="outline">
                    <a :href="route('bo.invoicing.invoices.pdf', invoice.id)"> <Download class="mr-2 h-4 w-4" /> Télécharger le PDF </a>
                </Button>
                <Button v-if="canSendEmail" variant="outline" @click="sendEmail">
                    <Mail class="mr-2 h-4 w-4" />
                    {{ emailSent ? 'Renvoyer par email' : 'Envoyer par email' }}
                </Button>
                <Button v-if="canPrintInvoice" variant="outline" :disabled="printing" @click="printCurrentInvoice">
                    <Printer class="mr-2 h-4 w-4" /> Imprimer
                </Button>
                <Button v-if="canChangeDeliveryMethod" variant="outline" @click="openDeliveryDialog">
                    <ArrowLeftRight class="mr-2 h-4 w-4" /> Mode d’envoi
                </Button>
                <Button v-if="documentFailed && canManageInvoicing" variant="outline" @click="retryDocument">
                    <RefreshCw class="mr-2 h-4 w-4" /> Réessayer l’archivage
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

        <div
            v-if="invoice.document && invoice.document.status !== 'stored'"
            class="border-destructive/40 bg-destructive/5 mx-4 rounded-xl border p-4 text-sm"
        >
            <p class="font-medium">
                {{ invoice.document.status === 'pending' ? 'Le PDF est en cours d’archivage.' : 'Le PDF n’a pas pu être archivé.' }}
            </p>
            <p v-if="invoice.document.error" class="text-muted-foreground mt-1">{{ invoice.document.error }}</p>
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
            <H3>Distribution</H3>
            <p v-if="!invoice.deliveries?.length" class="text-muted-foreground mt-4 text-sm">Aucune distribution enregistrée.</p>
            <div v-else class="mt-4 overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Canal</TableHead>
                            <TableHead>Destinataire</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead>Traité par</TableHead>
                            <TableHead>Détail</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="delivery in invoice.deliveries" :key="delivery.id">
                            <TableCell>{{ formatDate(delivery.created_at, 'DD.MM.YYYY HH:mm') }}</TableCell>
                            <TableCell>{{ channelLabel(delivery.channel) }}</TableCell>
                            <TableCell class="max-w-xs whitespace-normal">{{ delivery.recipient }}</TableCell>
                            <TableCell>
                                <Badge variant="secondary">{{ deliveryStatusLabel(delivery.status) }}</Badge>
                            </TableCell>
                            <TableCell>{{ delivery.processor?.name ?? '—' }}</TableCell>
                            <TableCell class="text-muted-foreground max-w-sm whitespace-normal">
                                <span v-if="delivery.error" class="text-destructive">{{ delivery.error }}</span>
                                <span v-else-if="delivery.processed_at">{{ formatDate(delivery.processed_at, 'DD.MM.YYYY HH:mm') }}</span>
                                <span v-else>—</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
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

        <Dialog v-model:open="deliveryDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mode de distribution</DialogTitle>
                    <DialogDescription>
                        Le mode peut être modifié tant qu’aucun envoi n’a commencé. Les coordonnées historiques de la facture sont conservées.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="delivery_method">Mode d’envoi</Label>
                    <Select v-model="deliveryMethodForm.delivery_method">
                        <SelectTrigger id="delivery_method" class="w-full">
                            <SelectValue placeholder="Sélectionner un mode" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="email">Email</SelectItem>
                            <SelectItem value="postal">Postal</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="deliveryMethodForm.errors.delivery_method" class="text-destructive text-sm">
                        {{ deliveryMethodForm.errors.delivery_method }}
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="deliveryDialogOpen = false">Annuler</Button>
                    <Button :disabled="deliveryMethodForm.processing" @click="submitDeliveryMethod">Enregistrer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
