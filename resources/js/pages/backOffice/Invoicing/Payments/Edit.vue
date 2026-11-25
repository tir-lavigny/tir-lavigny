<script setup lang="ts">
import PaymentFields from '@/components/back-office/Invoicing/PaymentFields.vue';
import { H1 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Payment } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    payment: Payment;
    methods: Array<{ value: string; label: string }>;
    hasActiveAllocations: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Paiements', href: route('bo.invoicing.payments.index') },
    { title: `Paiement #${props.payment.id}`, href: route('bo.invoicing.payments.show', props.payment.id) },
    { title: 'Modifier', href: route('bo.invoicing.payments.edit', props.payment.id) },
];

const form = useForm({
    amount: props.payment.amount,
    payment_date: props.payment.payment_date.slice(0, 10),
    method: props.payment.method,
    reference: props.payment.reference ?? '',
    notes: props.payment.notes ?? '',
});

const actionError = computed(() => (form.errors as Record<string, string>).action);

const submit = (): void => {
    form.put(route('bo.invoicing.payments.update', props.payment.id), {
        onError: () => toast.error('Le paiement n’a pas pu être mis à jour.'),
    });
};
</script>

<template>
    <Head :title="`Modifier le paiement #${payment.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Modifier le paiement</H1>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <div v-if="actionError" class="border-destructive/40 bg-destructive/5 mb-4 rounded-lg border p-3 text-sm">
                {{ actionError }}
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <PaymentFields
                    v-model:amount="form.amount"
                    v-model:method="form.method"
                    v-model:notes="form.notes"
                    v-model:payment-date="form.payment_date"
                    v-model:reference="form.reference"
                    :amount-disabled="hasActiveAllocations"
                    :amount-hint="hasActiveAllocations ? 'Retirez d’abord les imputations actives pour modifier le montant.' : null"
                    :errors="form.errors"
                    :methods="methods"
                />
                <div>
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
