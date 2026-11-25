<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, Invoice, PaginatedInvoices, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Mail, RefreshCw, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    invoices: PaginatedInvoices;
    filters: { search?: string | null; year?: number | null };
    years: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: 'Envois email', href: route('bo.invoicing.deliveries.email.index') },
];

const { filters } = useListFilters('bo.invoicing.deliveries.email.index', {
    search: props.filters.search || '',
    year: props.filters.year ? String(props.filters.year) : '',
});

const yearFilter = computed({
    get: () => filters.value.year || 'all',
    set: (value: string) => {
        filters.value.year = value === 'all' ? '' : value;
    },
});

const page = usePage<SharedData>();
const canSend = computed(() => hasAnyPermission(page.props.auth.user, ['send invoices', 'manage invoicing']));
const selectedIds = ref<number[]>([]);

const pendingTimedOut = (invoice: Invoice): boolean => {
    const delivery = invoice.latest_delivery;

    if (delivery?.status !== 'pending' || !delivery.created_at) {
        return false;
    }

    return Date.now() - new Date(delivery.created_at).getTime() >= 15 * 60 * 1000;
};

const isSendable = (invoice: Invoice): boolean =>
    invoice.document?.status === 'stored' && (invoice.latest_delivery?.status !== 'pending' || pendingTimedOut(invoice));

const selectableIds = computed(() => (canSend.value ? (props.invoices.data ?? []).filter(isSendable).map((invoice) => invoice.id) : []));
const allSelected = computed(() => selectableIds.value.length > 0 && selectableIds.value.every((id) => selectedIds.value.includes(id)));

const toggleAll = (checked: boolean | 'indeterminate'): void => {
    selectedIds.value = checked === true ? [...selectableIds.value] : [];
};

const toggleInvoice = (invoiceId: number, checked: boolean): void => {
    selectedIds.value = checked ? [...new Set([...selectedIds.value, invoiceId])] : selectedIds.value.filter((id) => id !== invoiceId);
};

const statusLabel = (invoice: Invoice): string => {
    const labels: Record<string, string> = {
        pending: 'En attente',
        sent: 'Envoyé',
        failed: 'Échec',
        printed: 'Imprimé',
    };

    return labels[invoice.latest_delivery?.status ?? ''] ?? 'Aucun envoi';
};

const sendOne = (invoice: Invoice): void => {
    router.post(
        route('bo.invoicing.invoices.email', invoice.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = selectedIds.value.filter((id) => id !== invoice.id);
            },
            onError: (errors) => toast.error(errors.action ?? "L'envoi a échoué."),
        },
    );
};

const sendSelected = (): void => {
    router.post(
        route('bo.invoicing.deliveries.email.send'),
        { invoice_ids: selectedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
            onError: (errors) => toast.error(errors.action ?? "L'envoi a échoué."),
        },
    );
};

const goToPage = (pageNumber: number): void => {
    router.get(route('bo.invoicing.deliveries.email.index'), { page: pageNumber, ...filters.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Envois email" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Envois email</H1>
                <p class="text-muted-foreground mt-2 text-sm">Factures émises en attente d’envoi par email.</p>
            </div>
            <Button v-if="canSend" :disabled="selectedIds.length === 0" @click="sendSelected">
                <Mail class="mr-2 h-4 w-4" />
                Envoyer la sélection ({{ selectedIds.length }})
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
                            <th class="w-10 px-4 py-3">
                                <Checkbox :model-value="allSelected" :disabled="selectableIds.length === 0" @update:model-value="toggleAll" />
                            </th>
                            <th class="px-4 py-3 text-left font-medium">Numéro</th>
                            <th class="px-4 py-3 text-left font-medium">Client</th>
                            <th class="px-4 py-3 text-left font-medium">Échéance</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-left font-medium">Dernière tentative</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="border-sidebar-border/70 dark:border-sidebar-border border-t">
                            <td class="px-4 py-3">
                                <Checkbox
                                    :model-value="selectedIds.includes(invoice.id)"
                                    :disabled="!canSend || !isSendable(invoice)"
                                    @update:model-value="(value) => toggleInvoice(invoice.id, value === true)"
                                />
                            </td>
                            <td class="px-4 py-3 font-mono">{{ invoice.invoice_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ invoice.recipient_name }}</div>
                                <div class="text-muted-foreground">{{ invoice.recipient_email || '—' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ invoice.due_date ? formatDate(invoice.due_date, 'DD.MM.YYYY') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(invoice.total) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-start gap-1">
                                    <Badge variant="secondary">{{ statusLabel(invoice) }}</Badge>
                                    <span v-if="invoice.document?.status !== 'stored'" class="text-destructive text-xs">PDF indisponible</span>
                                    <span v-else-if="invoice.latest_delivery?.error" class="text-destructive max-w-xs text-xs">
                                        {{ invoice.latest_delivery.error }}
                                    </span>
                                    <span v-else-if="invoice.latest_delivery?.processed_at" class="text-muted-foreground text-xs">
                                        {{ formatDate(invoice.latest_delivery.processed_at, 'DD.MM.YYYY HH:mm') }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="canSend" size="sm" variant="outline" :disabled="!isSendable(invoice)" @click="sendOne(invoice)">
                                    <RefreshCw v-if="invoice.latest_delivery?.status === 'failed'" class="mr-2 h-4 w-4" />
                                    <Mail v-else class="mr-2 h-4 w-4" />
                                    {{ invoice.latest_delivery?.status === 'failed' ? 'Réessayer' : 'Envoyer' }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!invoices.data?.length">
                            <td colspan="7" class="text-muted-foreground px-4 py-8 text-center">Aucune facture en attente d’envoi.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination :items-per-page="invoices.per_page" :total-items="invoices.total" :page="invoices.current_page" @page-changed="goToPage" />
        </div>
    </AppLayout>
</template>
