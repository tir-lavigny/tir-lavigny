<script setup lang="ts">
import BillingCustomerForm from '@/components/back-office/Invoicing/BillingCustomerForm.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BillingCustomer, BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    billingCustomer: BillingCustomer;
    types: Array<{ value: string; label: string }>;
    deliveryMethods: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clients de facturation', href: route('bo.invoicing.billing-customers.index') },
    { title: 'Modifier le client', href: route('bo.invoicing.billing-customers.edit', props.billingCustomer.id) },
];

const formDefaults = computed(() => ({
    type: props.billingCustomer.type,
    first_name: props.billingCustomer.first_name ?? '',
    last_name: props.billingCustomer.last_name ?? '',
    organization_name: props.billingCustomer.organization_name ?? '',
    address: props.billingCustomer.address,
    postal_code: props.billingCustomer.postal_code,
    city: props.billingCustomer.city,
    country: props.billingCustomer.country,
    email: props.billingCustomer.email ?? '',
    phone: props.billingCustomer.phone ?? '',
    preferred_invoice_delivery: props.billingCustomer.preferred_invoice_delivery,
}));

const submit = (form: any) => {
    form.put(route('bo.invoicing.billing-customers.update', props.billingCustomer.id), {
        onSuccess: () => toast.success('Client de facturation mis à jour.'),
        onError: (errors: Record<string, string>) => toast.error(errors.action ?? 'Erreur lors de la mise à jour.'),
    });
};

const syncMember = () => {
    router.post(
        route('bo.invoicing.billing-customers.sync', props.billingCustomer.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Client de facturation synchronisé.'),
            onError: (errors: Record<string, string>) => toast.error(errors.action ?? 'Erreur lors de la synchronisation.'),
        },
    );
};
</script>

<template>
    <Head :title="'Modifier ' + (billingCustomer.organization_name || billingCustomer.first_name + ' ' + billingCustomer.last_name)" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <H1>Modifier le client de facturation</H1>
            <Button v-if="billingCustomer.member_id" variant="outline" @click="syncMember">
                <RefreshCw class="mr-2 h-4 w-4" /> Synchroniser avec le membre
            </Button>
        </div>

        <div v-if="billingCustomer.member" class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mb-4 rounded-xl border p-4">
            <H3>Membre lié</H3>
            <p class="text-muted-foreground mt-2 text-sm">
                {{ billingCustomer.member.first_name }} {{ billingCustomer.member.last_name }}
                <span v-if="billingCustomer.member.email">· {{ billingCustomer.member.email }}</span>
            </p>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <BillingCustomerForm
                :form-defaults="formDefaults"
                :types="types"
                :delivery-methods="deliveryMethods"
                submit-label="Enregistrer"
                :on-submit="submit"
            />
        </div>
    </AppLayout>
</template>
