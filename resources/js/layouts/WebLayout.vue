<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import AppLayout from '@/layouts/web/AppHeaderLayout.vue';
import StagingBanner from '@/components/StagingBanner.vue';
</script>

<template>
    <StagingBanner />
    <Toaster position="top-center" />
    <AppLayout>
        <slot />
    </AppLayout>
</template>
