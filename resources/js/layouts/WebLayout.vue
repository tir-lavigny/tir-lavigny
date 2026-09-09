<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import AppLayout from '@/layouts/web/AppHeaderLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import StagingBanner from '@/components/StagingBanner.vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <StagingBanner />
    <Toaster position="top-center" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
