<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInvoicePrinting } from '@/composables/useInvoicePrinting';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, Invoice, PaginatedInvoices, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Printer, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    invoices: PaginatedInvoices;
    filters: { search?: string | null; year?: number | null };
    years: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: 'Impressions postales', href: route('bo.invoicing.deliveries.postal.index') },
];

const { filters } = useListFilters('bo.invoicing.deliveries.postal.index', {
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
const { printing, printInvoices } = useInvoicePrinting();
const selectedIds = ref<number[]>([]);

const isPrintable = (invoice: Invoice): boolean => invoice.document?.status === 'stored';
const selectableIds = computed(() => (canSend.value ? (props.invoices.data ?? []).filter(isPrintable).map((invoice) => invoice.id) : []));
const allSelected = computed(() => selectableIds.value.length > 0 && selectableIds.value.every((id) => selectedIds.value.includes(id)));

const toggleAll = (checked: boolean | 'indeterminate'): void => {
    selectedIds.value = checked === true ? [...selectableIds.value] : [];
};

const toggleInvoice = (invoiceId: number, checked: boolean): void => {
    selectedIds.value = checked ? [...new Set([...selectedIds.value, invoiceId])] : selectedIds.value.filter((id) => id !== invoiceId);
};

const printSelected = async (): Promise<void> => {
    if (await printInvoices(selectedIds.value)) {
        selectedIds.value = [];
    }
};

const printOne = async (invoice: Invoice): Promise<void> => {
    await printInvoices([invoice.id]);
};

const goToPage = (pageNumber: number): void => {
    router.get(route('bo.invoicing.deliveries.postal.index'), { page: pageNumber, ...filters.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Impressions postales" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Impressions postales</H1>
                <p class="text-muted-foreground mt-2 text-sm">Factures émises en attente d’impression.</p>
            </div>
            <Button v-if="canSend" :disabled="selectedIds.length === 0 || printing" @click="printSelected">
                <Printer class="mr-2 h-4 w-4" />
                Imprimer la sélection ({{ selectedIds.length }})
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
                            <th class="px-4 py-3 text-left font-medium">Destinataire</th>
                            <th class="px-4 py-3 text-left font-medium">Échéance</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-left font-medium">PDF</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="border-sidebar-border/70 dark:border-sidebar-border border-t">
                            <td class="px-4 py-3">
                                <Checkbox
                                    :model-value="selectedIds.includes(invoice.id)"
                                    :disabled="!canSend || !isPrintable(invoice)"
                                    @update:model-value="(value) => toggleInvoice(invoice.id, value === true)"
                                />
                            </td>
                            <td class="px-4 py-3 font-mono">{{ invoice.invoice_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ invoice.recipient_name }}</div>
                                <div class="text-muted-foreground">
                                    {{ invoice.recipient_address }}, {{ invoice.recipient_postal_code }} {{ invoice.recipient_city }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ invoice.due_date ? formatDate(invoice.due_date, 'DD.MM.YYYY') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(invoice.total) }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="isPrintable(invoice) ? 'secondary' : 'destructive'">
                                    {{ isPrintable(invoice) ? 'Archivé' : 'Indisponible' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="canSend"
                                    size="sm"
                                    variant="outline"
                                    :disabled="!isPrintable(invoice) || printing"
                                    @click="printOne(invoice)"
                                >
                                    <Printer class="mr-2 h-4 w-4" />
                                    Imprimer
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!invoices.data?.length">
                            <td colspan="7" class="text-muted-foreground px-4 py-8 text-center">Aucune facture en attente d’impression.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-muted-foreground text-xs">
                Le PDF généré contient les factures archivées complètes, y compris le QR-bill. Les factures sont marquées comme imprimées lors du
                téléchargement.
            </p>

            <AppPagination :items-per-page="invoices.per_page" :total-items="invoices.total" :page="invoices.current_page" @page-changed="goToPage" />
        </div>
    </AppLayout>
</template>
