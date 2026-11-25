<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedPayments, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Plus, Search } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    payments: PaginatedPayments;
    filters: { search?: string | null; method?: string | null; status?: string | null; year?: number | null };
    methods: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    years: number[];
}>();

const page = usePage<SharedData>();
const canCreate = computed(() => hasAnyPermission(page.props.auth.user, ['create payments', 'manage invoicing']));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Paiements', href: route('bo.invoicing.payments.index') }];

const { filters } = useListFilters('bo.invoicing.payments.index', {
    search: props.filters.search || '',
    method: props.filters.method || '',
    status: props.filters.status || '',
    year: props.filters.year ? String(props.filters.year) : '',
});

const methodFilter = computed({
    get: () => filters.value.method || 'all',
    set: (value: string) => {
        filters.value.method = value === 'all' ? '' : value;
    },
});

const statusFilter = computed({
    get: () => filters.value.status || 'all',
    set: (value: string) => {
        filters.value.status = value === 'all' ? '' : value;
    },
});

const yearFilter = computed({
    get: () => filters.value.year || 'all',
    set: (value: string) => {
        filters.value.year = value === 'all' ? '' : value;
    },
});

const goToPage = (pageNumber: number): void => {
    router.get(route('bo.invoicing.payments.index'), { page: pageNumber, ...filters.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Paiements" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Paiements</H1>
                <p class="text-muted-foreground mt-2 text-sm">Paiements reçus et imputations aux factures.</p>
            </div>
            <Button v-if="canCreate" as-child>
                <Link :href="route('bo.invoicing.payments.create')"><Plus class="mr-2 h-4 w-4" /> Nouveau paiement</Link>
            </Button>
        </div>

        <div class="mx-4 flex flex-col gap-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid flex-1 gap-2">
                    <Label for="search">Rechercher</Label>
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2" />
                        <Input id="search" v-model="filters.search" class="pl-8" placeholder="Référence ou notes" />
                    </div>
                </div>
                <div class="grid gap-2 md:w-48">
                    <Label for="method">Moyen</Label>
                    <Select v-model="methodFilter">
                        <SelectTrigger id="method" class="w-full"><SelectValue placeholder="Tous" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:w-40">
                    <Label for="status">Statut</Label>
                    <Select v-model="statusFilter">
                        <SelectTrigger id="status" class="w-full"><SelectValue placeholder="Tous" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:w-32">
                    <Label for="year">Année</Label>
                    <Select v-model="yearFilter">
                        <SelectTrigger id="year" class="w-full"><SelectValue placeholder="Toutes" /></SelectTrigger>
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
                            <th class="px-4 py-3 text-left font-medium">Date</th>
                            <th class="px-4 py-3 text-left font-medium">Moyen</th>
                            <th class="px-4 py-3 text-left font-medium">Référence</th>
                            <th class="px-4 py-3 text-right font-medium">Montant</th>
                            <th class="px-4 py-3 text-right font-medium">Alloué</th>
                            <th class="px-4 py-3 text-right font-medium">Non alloué</th>
                            <th class="px-4 py-3 text-left font-medium">Statut</th>
                            <th class="px-4 py-3 text-left font-medium">Enregistré par</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in payments.data" :key="payment.id" class="border-sidebar-border/70 dark:border-sidebar-border border-t">
                            <td class="px-4 py-3">{{ formatDate(payment.payment_date, 'DD.MM.YYYY') }}</td>
                            <td class="px-4 py-3">{{ payment.method_label }}</td>
                            <td class="px-4 py-3">{{ payment.reference || '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(payment.amount) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney((payment.allocated_cents ?? 0) / 100) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney((payment.unallocated_cents ?? 0) / 100) }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="payment.voided_at ? 'destructive' : 'secondary'">{{ payment.voided_at ? 'Annulé' : 'Actif' }}</Badge>
                            </td>
                            <td class="px-4 py-3">{{ payment.creator?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    <Button as-child size="icon" variant="ghost">
                                        <Link :href="route('bo.invoicing.payments.show', payment.id)">
                                            <Eye class="h-4 w-4" />
                                            <span class="sr-only">Voir</span>
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!payments.data?.length">
                            <td colspan="9" class="text-muted-foreground px-4 py-8 text-center">Aucun paiement enregistré.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination :items-per-page="payments.per_page" :total-items="payments.total" :page="payments.current_page" @page-changed="goToPage" />
        </div>
    </AppLayout>
</template>
