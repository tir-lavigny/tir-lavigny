<script setup lang="ts">
import BillingCustomerForm from '@/components/back-office/Invoicing/BillingCustomerForm.vue';
import { H1 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

defineProps<{
    types: Array<{ value: string; label: string }>;
    deliveryMethods: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clients de facturation', href: route('bo.invoicing.billing-customers.index') },
    { title: 'Nouveau client', href: route('bo.invoicing.billing-customers.create') },
];

const formDefaults = {
    type: 'individual',
    first_name: '',
    last_name: '',
    organization_name: '',
    address: '',
    postal_code: '',
    city: '',
    country: 'CH',
    email: '',
    phone: '',
    preferred_invoice_delivery: 'email',
    sync_with_member: true,
};

const submit = (form: any) => {
    form.post(route('bo.invoicing.billing-customers.store'), {
        onSuccess: () => toast.success('Client de facturation créé.'),
        onError: () => toast.error('Erreur lors de la création.'),
    });
};
</script>

<template>
    <Head title="Nouveau client de facturation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Nouveau client de facturation</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <BillingCustomerForm
                :form-defaults="formDefaults"
                :types="types"
                :delivery-methods="deliveryMethods"
                submit-label="Créer"
                :on-submit="submit"
            />
        </div>
    </AppLayout>
</template>
