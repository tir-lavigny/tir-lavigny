<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, CreditNote, PaginatedCreditNotes, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    creditNotes: PaginatedCreditNotes;
    filters: { search?: string | null; status?: string | null };
    statuses: Array<{ value: string; label: string }>;
}>();

const page = usePage<SharedData>();
const user = page.props.auth.user;
const canCreate = computed(() => hasAnyPermission(user, ['create invoices', 'manage invoicing']));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Avoirs', href: route('bo.invoicing.credit-notes.index') }];

const { filters } = useListFilters('bo.invoicing.credit-notes.index', {
    search: props.filters.search || '',
    status: props.filters.status || '',
});

const statusFilter = computed({
    get: () => filters.value.status || 'all',
    set: (value: string) => {
        filters.value.status = value === 'all' ? '' : value;
    },
});

const deleteCreditNote = (creditNote: CreditNote) => {
    router.delete(route('bo.invoicing.credit-notes.destroy', creditNote.id), {
        preserveScroll: true,
        onSuccess: () => toast.success('Avoir brouillon supprimé.'),
        onError: (errors) => toast.error(errors.action ?? "Erreur lors de la suppression de l'avoir."),
    });
};

const statusLabel = (value: string): string => props.statuses.find((status) => status.value === value)?.label ?? value;

const goToPage = (pageNumber: number) => {
    router.get(route('bo.invoicing.credit-notes.index'), { page: pageNumber, ...filters.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Avoirs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Avoirs</H1>
        </div>

        <div class="mx-4 flex flex-col gap-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid flex-1 gap-2">
                    <Label for="search">Rechercher</Label>
                    <Input id="search" v-model="filters.search" placeholder="Numéro ou client" />
                </div>
                <div class="grid gap-2 md:w-56">
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
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Numéro</th>
                            <th class="px-4 py-3 text-left font-medium">Facture</th>
                            <th class="px-4 py-3 text-left font-medium">Client</th>
                            <th class="px-4 py-3 text-left font-medium">Statut</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="creditNote in creditNotes.data"
                            :key="creditNote.id"
                            class="border-sidebar-border/70 dark:border-sidebar-border border-t"
                        >
                            <td class="px-4 py-3 font-mono">{{ creditNote.credit_note_number ?? 'Brouillon' }}</td>
                            <td class="px-4 py-3 font-mono">
                                <Link class="underline" :href="route('bo.invoicing.invoices.show', creditNote.invoice_id)">
                                    {{ creditNote.invoice?.invoice_number ?? `#${creditNote.invoice_id}` }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">{{ creditNote.recipient_name }}</td>
                            <td class="px-4 py-3">
                                <Badge variant="secondary">{{ statusLabel(creditNote.status) }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(creditNote.total) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button as-child size="icon" variant="ghost">
                                        <Link :href="route('bo.invoicing.credit-notes.show', creditNote.id)">
                                            <Eye class="h-4 w-4" />
                                            <span class="sr-only">Voir</span>
                                        </Link>
                                    </Button>
                                    <AppAlertDialog
                                        v-if="creditNote.status === 'draft' && canCreate"
                                        title="Supprimer ce brouillon ?"
                                        description="L'avoir brouillon sera supprimé définitivement."
                                        cancel-text="Annuler"
                                    >
                                        <template #trigger>
                                            <Button size="icon" variant="ghost">
                                                <Trash2 class="h-4 w-4" />
                                                <span class="sr-only">Supprimer</span>
                                            </Button>
                                        </template>
                                        <template #action>
                                            <Button variant="destructive" @click="deleteCreditNote(creditNote)">Supprimer</Button>
                                        </template>
                                    </AppAlertDialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!creditNotes.data?.length" class="text-muted-foreground px-4 py-8 text-center">Aucun avoir trouvé.</div>
            </div>

            <AppPagination
                :items-per-page="creditNotes.per_page"
                :total-items="creditNotes.total"
                :page="creditNotes.current_page"
                @page-changed="goToPage"
            />
        </div>
    </AppLayout>
</template>
