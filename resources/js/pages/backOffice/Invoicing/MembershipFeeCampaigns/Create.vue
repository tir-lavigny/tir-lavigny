<script setup lang="ts">
import MembershipFeeCampaignForm from '@/components/back-office/Invoicing/MembershipFeeCampaignForm.vue';
import { H1 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campagnes de cotisation', href: route('bo.invoicing.membership-fee-campaigns.index') },
    { title: 'Nouvelle campagne', href: route('bo.invoicing.membership-fee-campaigns.create') },
];

const localDate = (date: Date): string => {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const nextYear = new Date().getFullYear() + 1;
const issueDate = new Date(nextYear, 0, 1);
const dueDate = new Date(nextYear, 1, 1);

const formDefaults = {
    year: String(nextYear),
    name: `Cotisations ${nextYear}`,
    issue_date: localDate(issueDate),
    due_date: localDate(dueDate),
};

const submit = (form: any) => {
    form.post(route('bo.invoicing.membership-fee-campaigns.store'));
};
</script>

<template>
    <Head title="Nouvelle campagne de cotisation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Nouvelle campagne de cotisation</H1>
            <p class="text-muted-foreground mt-2 text-sm">Créez la campagne, puis générez l’aperçu pour calculer les cotisations de chaque membre.</p>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <MembershipFeeCampaignForm :form-defaults="formDefaults" submit-label="Créer" :on-submit="submit" />
        </div>
    </AppLayout>
</template>
