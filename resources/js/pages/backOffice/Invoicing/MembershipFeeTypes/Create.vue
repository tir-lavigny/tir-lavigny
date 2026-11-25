<script setup lang="ts">
import MembershipFeeTypeForm from '@/components/back-office/Invoicing/MembershipFeeTypeForm.vue';
import { H1 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    memberStatuses: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Types de cotisation', href: route('bo.invoicing.membership-fee-types.index') },
    { title: 'Nouveau type', href: route('bo.invoicing.membership-fee-types.create') },
];

const formDefaults = {
    name: '',
    member_status: 'active',
    description: '',
    amount: '',
    is_active: true,
};

const submit = (form: any) => {
    form.post(route('bo.invoicing.membership-fee-types.store'));
};
</script>

<template>
    <Head title="Nouveau type de cotisation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Nouveau type de cotisation</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <MembershipFeeTypeForm :form-defaults="formDefaults" :member-statuses="memberStatuses" submit-label="Créer" :on-submit="submit" />
        </div>
    </AppLayout>
</template>
