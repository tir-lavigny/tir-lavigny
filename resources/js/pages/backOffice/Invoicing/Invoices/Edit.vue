<script setup lang="ts">
import InvoiceForm from '@/components/back-office/Invoicing/InvoiceForm.vue';
import { H1 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Invoice } from '@/types';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const props = defineProps<{
    invoice: Invoice;
    billingCustomers: Array<{ id: number; name: string; email: string | null }>;
    deliveryMethods: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: props.invoice.invoice_number ?? `Brouillon #${props.invoice.id}`, href: route('bo.invoicing.invoices.show', props.invoice.id) },
    { title: 'Modifier', href: route('bo.invoicing.invoices.edit', props.invoice.id) },
];

const formDefaults = {
    billing_customer_id: props.invoice.billing_customer_id,
    delivery_method: props.invoice.delivery_method,
    issue_date: props.invoice.issue_date?.slice(0, 10) ?? '',
    due_date: props.invoice.due_date?.slice(0, 10) ?? '',
    items: (props.invoice.items ?? []).map((item) => ({
        type: item.type,
        description: item.description,
        quantity: String(item.quantity),
        unit_price: item.unit_price,
        product_id: item.product_id,
        metadata: item.metadata ?? null,
    })),
};

const submit = (form: any) => {
    form.put(route('bo.invoicing.invoices.update', props.invoice.id), {
        onSuccess: () => toast.success('Facture brouillon mise à jour.'),
        onError: () => toast.error('Erreur lors de la mise à jour.'),
    });
};
</script>

<template>
    <Head :title="'Modifier ' + (invoice.invoice_number ?? `brouillon #${invoice.id}`)" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Modifier la facture</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <InvoiceForm
                :form-defaults="formDefaults"
                :billing-customers="billingCustomers"
                :delivery-methods="deliveryMethods"
                :preview-url="route('bo.invoicing.invoices.preview')"
                submit-label="Enregistrer"
                :on-submit="submit"
            />
        </div>
    </AppLayout>
</template>
