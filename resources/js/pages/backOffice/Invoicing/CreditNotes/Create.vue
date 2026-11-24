<script setup lang="ts">
import CreditNoteForm from '@/components/back-office/Invoicing/CreditNoteForm.vue';
import { H1, H3 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Invoice } from '@/types';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const props = defineProps<{
    invoice: Invoice;
    remainingCreditableCents: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: props.invoice.invoice_number ?? `Brouillon #${props.invoice.id}`, href: route('bo.invoicing.invoices.show', props.invoice.id) },
    { title: 'Nouvel avoir', href: route('bo.invoicing.credit-notes.create', { invoice_id: props.invoice.id }) },
];

const localDate = (date: Date): string => {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const formDefaults = {
    invoice_id: props.invoice.id,
    reason: '',
    issue_date: localDate(new Date()),
    items: (props.invoice.items ?? []).map((item) => ({
        invoice_item_id: item.id,
        description: item.description,
        quantity: String(item.quantity),
        unit_price: item.unit_price,
    })),
};

const submit = (form: any) => {
    form.post(route('bo.invoicing.credit-notes.store'), {
        onSuccess: () => toast.success('Avoir brouillon créé.'),
        onError: () => toast.error("Erreur lors de la création de l'avoir."),
    });
};
</script>

<template>
    <Head title="Nouvel avoir" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Nouvel avoir</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mb-4 rounded-xl border p-4">
            <H3>Facture d'origine</H3>
            <p class="mt-2 text-sm">{{ invoice.invoice_number }} · {{ invoice.recipient_name }} · Total {{ invoice.total }}</p>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <CreditNoteForm
                :form-defaults="formDefaults"
                :preview-url="route('bo.invoicing.credit-notes.preview')"
                :remaining-creditable-cents="remainingCreditableCents"
                submit-label="Enregistrer le brouillon"
                :on-submit="submit"
            />
        </div>
    </AppLayout>
</template>
