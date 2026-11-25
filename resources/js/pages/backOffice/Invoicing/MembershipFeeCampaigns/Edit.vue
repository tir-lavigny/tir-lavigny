<script setup lang="ts">
import MembershipFeeCampaignForm from '@/components/back-office/Invoicing/MembershipFeeCampaignForm.vue';
import { H1 } from '@/components/typography/headings';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, MembershipFeeCampaign } from '@/types';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
    campaign: MembershipFeeCampaign;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campagnes de cotisation', href: route('bo.invoicing.membership-fee-campaigns.index') },
    { title: props.campaign.name, href: route('bo.invoicing.membership-fee-campaigns.show', props.campaign.id) },
    { title: 'Modifier', href: route('bo.invoicing.membership-fee-campaigns.edit', props.campaign.id) },
];

const formDefaults = {
    year: String(props.campaign.year),
    name: props.campaign.name,
    issue_date: props.campaign.issue_date.slice(0, 10),
    due_date: props.campaign.due_date.slice(0, 10),
};

const submit = (form: any) => {
    form.put(route('bo.invoicing.membership-fee-campaigns.update', props.campaign.id), {
        onError: (errors: Record<string, string>) => {
            if (errors.action) {
                router.visit(route('bo.invoicing.membership-fee-campaigns.show', props.campaign.id));
            }
        },
    });
};
</script>

<template>
    <Head :title="'Modifier ' + campaign.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Modifier la campagne</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <MembershipFeeCampaignForm :form-defaults="formDefaults" submit-label="Enregistrer" :on-submit="submit" />
        </div>
    </AppLayout>
</template>
