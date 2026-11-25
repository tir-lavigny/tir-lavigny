<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import StagingBanner from '@/components/StagingBanner.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <StagingBanner />
        <Toaster position="top-center" />
        <AppLayout :breadcrumbs="breadcrumbs">
            <slot />
        </AppLayout>
    </TooltipProvider>
</template>
