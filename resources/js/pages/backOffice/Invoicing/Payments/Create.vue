<script setup lang="ts">
import PaymentFields from '@/components/back-office/Invoicing/PaymentFields.vue';
import { H1 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const props = defineProps<{
    methods: Array<{ value: string; label: string }>;
    idempotencyKey: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Paiements', href: route('bo.invoicing.payments.index') },
    { title: 'Nouveau paiement', href: route('bo.invoicing.payments.create') },
];

const localDate = (date: Date): string => {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const form = useForm({
    amount: '',
    payment_date: localDate(new Date()),
    method: 'bank_transfer',
    reference: '',
    notes: '',
    idempotency_key: props.idempotencyKey,
});

const submit = (): void => {
    form.post(route('bo.invoicing.payments.store'), {
        onError: () => toast.error('Vérifiez les informations du paiement.'),
    });
};
</script>

<template>
    <Head title="Nouveau paiement" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Nouveau paiement</H1>
            <p class="text-muted-foreground mt-2 text-sm">
                Le paiement sera enregistré sans imputation. Vous pourrez ensuite l’allouer à une ou plusieurs factures.
            </p>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <PaymentFields
                    v-model:amount="form.amount"
                    v-model:method="form.method"
                    v-model:notes="form.notes"
                    v-model:payment-date="form.payment_date"
                    v-model:reference="form.reference"
                    :errors="form.errors"
                    :methods="methods"
                />
                <div>
                    <Button type="submit" :disabled="form.processing">Enregistrer le paiement</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
