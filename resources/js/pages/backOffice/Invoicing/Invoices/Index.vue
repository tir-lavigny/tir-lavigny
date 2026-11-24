<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, Invoice, PaginatedInvoices, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Eye, Pencil, Plus, Search, Trash2, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    invoices: PaginatedInvoices;
    filters: { search?: string | null; status?: string | null; type?: string | null; year?: number | null };
    statuses: Array<{ value: string; label: string }>;
    types: Array<{ value: string; label: string }>;
    years: number[];
}>();

const page = usePage<SharedData>();
const user = page.props.auth.user;

const canCreate = computed(() => hasAnyPermission(user, ['create invoices', 'manage invoicing']));
const canIssue = computed(() => hasAnyPermission(user, ['issue invoices', 'manage invoicing']));
const canCancel = computed(() => hasAnyPermission(user, ['cancel invoices', 'manage invoicing']));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Factures', href: route('bo.invoicing.invoices.index') }];

const { filters } = useListFilters('bo.invoicing.invoices.index', {
    search: props.filters.search || '',
    status: props.filters.status || '',
    type: props.filters.type || '',
    year: props.filters.year ? String(props.filters.year) : '',
});

const statusFilter = computed({
    get: () => filters.value.status || 'all',
    set: (value: string) => {
        filters.value.status = value === 'all' ? '' : value;
    },
});

const typeFilter = computed({
    get: () => filters.value.type || 'all',
    set: (value: string) => {
        filters.value.type = value === 'all' ? '' : value;
    },
});

const yearFilter = computed({
    get: () => filters.value.year || 'all',
    set: (value: string) => {
        filters.value.year = value === 'all' ? '' : value;
    },
});

const cancelDialogOpen = ref(false);
const cancelTarget = ref<Invoice | null>(null);
const cancelForm = useForm({ reason: '' });

const issueInvoice = (invoice: Invoice) => {
    router.post(
        route('bo.invoicing.invoices.issue', invoice.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Facture émise.'),
            onError: (errors) => toast.error(errors.action ?? "Erreur lors de l'émission."),
        },
    );
};

const openCancelDialog = (invoice: Invoice) => {
    cancelTarget.value = invoice;
    cancelForm.reset();
    cancelDialogOpen.value = true;
};

const submitCancel = () => {
    if (!cancelTarget.value) {
        return;
    }

    cancelForm.post(route('bo.invoicing.invoices.cancel', cancelTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelDialogOpen.value = false;
            toast.success('Facture annulée.');
        },
        onError: (errors) => toast.error(errors.action ?? errors.reason ?? "Erreur lors de l'annulation."),
    });
};

const deleteInvoice = (invoice: Invoice) => {
    router.delete(route('bo.invoicing.invoices.destroy', invoice.id), {
        preserveScroll: true,
        onSuccess: () => toast.success('Facture brouillon supprimée.'),
        onError: (errors) => toast.error(errors.action ?? 'Erreur lors de la suppression.'),
    });
};

const statusLabel = (value: string): string => props.statuses.find((status) => status.value === value)?.label ?? value;
const typeLabel = (value: string): string => props.types.find((type) => type.value === value)?.label ?? value;

const goToPage = (pageNumber: number) => {
    router.get(route('bo.invoicing.invoices.index'), { page: pageNumber, ...filters.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Factures" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <H1>Factures</H1>
            <Button v-if="canCreate" as-child>
                <Link :href="route('bo.invoicing.invoices.create')"> <Plus class="mr-2 h-4 w-4" /> Nouvelle facture </Link>
            </Button>
        </div>

        <div class="mx-4 flex flex-col gap-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid flex-1 gap-2">
                    <Label for="search">Rechercher</Label>
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2" />
                        <Input id="search" v-model="filters.search" class="pl-8" placeholder="Numéro ou client" />
                    </div>
                </div>
                <div class="grid gap-2 md:w-44">
                    <Label for="status">Statut</Label>
                    <Select v-model="statusFilter">
                        <SelectTrigger id="status" class="w-full">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:w-44">
                    <Label for="type">Type</Label>
                    <Select v-model="typeFilter">
                        <SelectTrigger id="type" class="w-full">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:w-36">
                    <Label for="year">Année</Label>
                    <Select v-model="yearFilter">
                        <SelectTrigger id="year" class="w-full">
                            <SelectValue placeholder="Toutes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Toutes</SelectItem>
                            <SelectItem v-for="year in years" :key="year" :value="String(year)">{{ year }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Numéro</th>
                            <th class="px-4 py-3 text-left font-medium">Client</th>
                            <th class="px-4 py-3 text-left font-medium">Type</th>
                            <th class="px-4 py-3 text-left font-medium">Statut</th>
                            <th class="px-4 py-3 text-left font-medium">Échéance</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-right font-medium">Reste à payer</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="border-sidebar-border/70 dark:border-sidebar-border border-t">
                            <td class="px-4 py-3 font-mono">{{ invoice.invoice_number ?? 'Brouillon' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ invoice.recipient_name }}</div>
                                <div class="text-muted-foreground">{{ invoice.recipient_email || '—' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ typeLabel(invoice.type) }}</td>
                            <td class="px-4 py-3">
                                <Badge variant="secondary">{{ statusLabel(invoice.status) }}</Badge>
                            </td>
                            <td class="px-4 py-3">{{ invoice.due_date ? formatDate(invoice.due_date, 'DD.MM.YYYY') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(invoice.total) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney((invoice.remaining_cents ?? 0) / 100) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button as-child size="icon" variant="ghost">
                                        <Link :href="route('bo.invoicing.invoices.show', invoice.id)">
                                            <Eye class="h-4 w-4" />
                                            <span class="sr-only">Voir</span>
                                        </Link>
                                    </Button>
                                    <Button v-if="invoice.status === 'draft' && canCreate" as-child size="icon" variant="ghost">
                                        <Link :href="route('bo.invoicing.invoices.edit', invoice.id)">
                                            <Pencil class="h-4 w-4" />
                                            <span class="sr-only">Modifier</span>
                                        </Link>
                                    </Button>
                                    <AppAlertDialog
                                        v-if="invoice.status === 'draft' && canIssue"
                                        title="Émettre cette facture ?"
                                        description="Un numéro définitif sera attribué. La facture ne pourra plus être modifiée."
                                        cancel-text="Annuler"
                                    >
                                        <template #trigger>
                                            <Button size="icon" variant="ghost">
                                                <CheckCircle2 class="h-4 w-4" />
                                                <span class="sr-only">Émettre</span>
                                            </Button>
                                        </template>
                                        <template #action>
                                            <Button @click="issueInvoice(invoice)">Émettre</Button>
                                        </template>
                                    </AppAlertDialog>
                                    <Button
                                        v-if="invoice.status === 'issued' && canCancel"
                                        size="icon"
                                        variant="ghost"
                                        @click="openCancelDialog(invoice)"
                                    >
                                        <XCircle class="h-4 w-4" />
                                        <span class="sr-only">Annuler</span>
                                    </Button>
                                    <AppAlertDialog
                                        v-if="invoice.status === 'draft' && canCreate"
                                        title="Supprimer ce brouillon ?"
                                        description="Le brouillon sera supprimé définitivement."
                                        cancel-text="Annuler"
                                    >
                                        <template #trigger>
                                            <Button size="icon" variant="ghost">
                                                <Trash2 class="h-4 w-4" />
                                                <span class="sr-only">Supprimer</span>
                                            </Button>
                                        </template>
                                        <template #action>
                                            <Button variant="destructive" @click="deleteInvoice(invoice)">Supprimer</Button>
                                        </template>
                                    </AppAlertDialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!invoices.data?.length" class="text-muted-foreground px-4 py-8 text-center">Aucune facture trouvée.</div>
            </div>

            <AppPagination :items-per-page="invoices.per_page" :total-items="invoices.total" :page="invoices.current_page" @page-changed="goToPage" />
        </div>

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
