<script setup lang="ts">
import MembershipFeeTypeForm from '@/components/back-office/Invoicing/MembershipFeeTypeForm.vue';
import { H1 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, MembershipFeeType } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    feeType: MembershipFeeType;
    memberStatuses: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Types de cotisation', href: route('bo.invoicing.membership-fee-types.index') },
    { title: props.feeType.name, href: route('bo.invoicing.membership-fee-types.edit', props.feeType.id) },
];

const formDefaults = {
    name: props.feeType.name,
    member_status: props.feeType.member_status ?? 'active',
    description: props.feeType.description ?? '',
    amount: props.feeType.amount,
};

const submit = (form: any) => {
    form.put(route('bo.invoicing.membership-fee-types.update', props.feeType.id));
};
</script>

<template>
    <Head :title="'Modifier ' + feeType.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Modifier le type de cotisation</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <MembershipFeeTypeForm :form-defaults="formDefaults" :member-statuses="memberStatuses" submit-label="Enregistrer" :on-submit="submit" />
        </div>
    </AppLayout>
</template>
