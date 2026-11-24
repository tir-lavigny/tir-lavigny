<script setup lang="ts">
import InvoiceForm from '@/components/back-office/Invoicing/InvoiceForm.vue';
import { H1 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

defineProps<{
    billingCustomers: Array<{ id: number; name: string; email: string | null; preferred_invoice_delivery: string }>;
    deliveryMethods: Array<{ value: string; label: string }>;
    itemTypes: Array<{ value: string; label: string; description: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: 'Nouvelle facture', href: route('bo.invoicing.invoices.create') },
];

const localDate = (date: Date): string => {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const today = new Date();
const dueDate = new Date();
dueDate.setDate(dueDate.getDate() + 30);

const formDefaults = {
    billing_customer_id: '',
    delivery_method: 'email',
    issue_date: localDate(today),
    due_date: localDate(dueDate),
    items: [],
};

const submit = (form: any) => {
    form.post(route('bo.invoicing.invoices.store'), {
        onSuccess: () => toast.success('Facture brouillon créée.'),
        onError: () => toast.error('Erreur lors de la création.'),
    });
};
</script>

<template>
    <Head title="Nouvelle facture" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Nouvelle facture</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <InvoiceForm
                :form-defaults="formDefaults"
                :billing-customers="billingCustomers"
                :delivery-methods="deliveryMethods"
                :item-types="itemTypes"
                :preview-url="route('bo.invoicing.invoices.preview')"
                submit-label="Enregistrer le brouillon"
                :on-submit="submit"
            />
        </div>
    </AppLayout>
</template>
