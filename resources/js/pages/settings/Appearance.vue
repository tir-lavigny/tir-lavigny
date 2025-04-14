<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: "Paramètres d'apparence",
        href: route('appearance'),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Paramètres d'apparence" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Paramètres d'apparence" description="Mettez à jour les paramètres d'apparence de votre compte" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
