<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import AppTooltipIconButton from '@/components/AppTooltipIconButton.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInvoiceReminderPrinting } from '@/composables/useInvoiceReminderPrinting';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney } from '@/lib/utils';
import type { BreadcrumbItem, Invoice, PaginatedInvoices } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BellRing, Eye, Mail, Printer, Search } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    invoices: PaginatedInvoices;
    filters: { search?: string | null; year?: number | null };
    years: number[];
    canSendReminders: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: 'Rappels', href: route('bo.invoicing.reminders.overdue.index') },
];

const { filters } = useListFilters('bo.invoicing.reminders.overdue.index', {
    search: props.filters.search || '',
    year: props.filters.year ? String(props.filters.year) : '',
});

const yearFilter = computed({
    get: () => filters.value.year || 'all',
    set: (value: string) => {
        filters.value.year = value === 'all' ? '' : value;
    },
});

const { printing, printReminder } = useInvoiceReminderPrinting();

const sendEmail = (invoice: Invoice): void => {
    router.post(
        route('bo.invoicing.invoices.reminders.email', invoice.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Rappel email mis en file d’attente.'),
            onError: (errors) => toast.error(errors.action ?? "Le rappel email n'a pas pu être envoyé."),
        },
    );
};

const print = async (invoice: Invoice): Promise<void> => {
    await printReminder(invoice.id);
};

const reminderStatusLabel = (status: string | undefined): string => {
    const labels: Record<string, string> = {
        pending: 'En attente',
        sent: 'Envoyé',
        failed: 'Échec',
        printed: 'Imprimé',
    };

    return labels[status ?? ''] ?? 'Aucun rappel';
};

const goToPage = (pageNumber: number): void => {
    router.get(route('bo.invoicing.reminders.overdue.index'), { page: pageNumber, ...filters.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Rappels" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Rappels</H1>
                <p class="text-muted-foreground mt-2 text-sm">Factures émises en retard avec un solde impayé.</p>
            </div>
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
                            <th class="px-4 py-3 text-left font-medium">Numéro</th>
                            <th class="px-4 py-3 text-left font-medium">Client</th>
                            <th class="px-4 py-3 text-left font-medium">Échéance</th>
                            <th class="px-4 py-3 text-right font-medium">Retard</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-right font-medium">Solde</th>
                            <th class="px-4 py-3 text-left font-medium">Dernier rappel</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="border-sidebar-border/70 dark:border-sidebar-border border-t">
                            <td class="px-4 py-3 font-mono">{{ invoice.invoice_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ invoice.recipient_name }}</div>
                                <div class="text-muted-foreground">{{ invoice.recipient_email || 'Postal' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ invoice.due_date ? formatDate(invoice.due_date, 'DD.MM.YYYY') : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <Badge variant="destructive">{{ invoice.days_overdue ?? 0 }} j</Badge>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(invoice.total) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney((invoice.remaining_cents ?? 0) / 100) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-start gap-1">
                                    <Badge variant="secondary">{{ reminderStatusLabel(invoice.latest_reminder?.status) }}</Badge>
                                    <span v-if="invoice.latest_reminder?.processed_at" class="text-muted-foreground text-xs">
                                        {{ formatDate(invoice.latest_reminder.processed_at, 'DD.MM.YYYY HH:mm') }}
                                    </span>
                                    <span v-if="invoice.latest_reminder?.error" class="text-destructive max-w-xs text-xs">
                                        {{ invoice.latest_reminder.error }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <AppTooltipIconButton label="Voir la facture" :href="route('bo.invoicing.invoices.show', invoice.id)">
                                        <Eye class="h-4 w-4" />
                                    </AppTooltipIconButton>
                                    <AppAlertDialog
                                        v-if="canSendReminders && invoice.recipient_email"
                                        title="Envoyer un rappel par email ?"
                                        :description="`Un email avec le solde impayé et le PDF de rappel sera envoyé à ${invoice.recipient_email}.`"
                                        cancel-text="Annuler"
                                    >
                                        <template #trigger>
                                            <AppTooltipIconButton label="Envoyer un rappel par email">
                                                <Mail class="h-4 w-4" />
                                            </AppTooltipIconButton>
                                        </template>
                                        <template #action>
                                            <Button @click="sendEmail(invoice)"><BellRing class="mr-2 h-4 w-4" /> Envoyer</Button>
                                        </template>
                                    </AppAlertDialog>
                                    <AppTooltipIconButton
                                        v-if="canSendReminders"
                                        label="Imprimer un rappel postal"
                                        :disabled="printing"
                                        @click="print(invoice)"
                                    >
                                        <Printer class="h-4 w-4" />
                                    </AppTooltipIconButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!invoices.data?.length">
                            <td colspan="8" class="text-muted-foreground px-4 py-8 text-center">Aucune facture en retard.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination :items-per-page="invoices.per_page" :total-items="invoices.total" :page="invoices.current_page" @page-changed="goToPage" />
        </div>
    </AppLayout>
</template>
