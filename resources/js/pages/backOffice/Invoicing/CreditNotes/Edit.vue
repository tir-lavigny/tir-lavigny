<script setup lang="ts">
import CreditNoteForm from '@/components/back-office/Invoicing/CreditNoteForm.vue';
import { H1, H3 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CreditNote } from '@/types';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const props = defineProps<{
    creditNote: CreditNote;
    remainingCreditableCents: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avoirs', href: route('bo.invoicing.credit-notes.index') },
    {
        title: props.creditNote.credit_note_number ?? `Brouillon #${props.creditNote.id}`,
        href: route('bo.invoicing.credit-notes.show', props.creditNote.id),
    },
    { title: 'Modifier', href: route('bo.invoicing.credit-notes.edit', props.creditNote.id) },
];

const formDefaults = {
    invoice_id: props.creditNote.invoice_id,
    reason: props.creditNote.reason,
    issue_date: props.creditNote.issue_date?.slice(0, 10) ?? '',
    items: (props.creditNote.items ?? []).map((item) => ({
        invoice_item_id: item.invoice_item_id,
        description: item.description,
        quantity: String(item.quantity),
        unit_price: item.unit_price,
    })),
};

const submit = (form: any) => {
    form.put(route('bo.invoicing.credit-notes.update', props.creditNote.id), {
        onSuccess: () => toast.success('Avoir brouillon mis à jour.'),
        onError: () => toast.error("Erreur lors de la mise à jour de l'avoir."),
    });
};
</script>

<template>
    <Head :title="'Modifier ' + (creditNote.credit_note_number ?? `brouillon #${creditNote.id}`)" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Modifier l'avoir</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mb-4 rounded-xl border p-4">
            <H3>Facture d'origine</H3>
            <p class="mt-2 text-sm">{{ creditNote.invoice?.invoice_number }} · {{ creditNote.recipient_name }} · Total {{ creditNote.total }}</p>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <CreditNoteForm
                :form-defaults="formDefaults"
                :preview-url="route('bo.invoicing.credit-notes.preview')"
                :remaining-creditable-cents="remainingCreditableCents"
                submit-label="Enregistrer"
                :on-submit="submit"
            />
        </div>
    </AppLayout>
</template>
