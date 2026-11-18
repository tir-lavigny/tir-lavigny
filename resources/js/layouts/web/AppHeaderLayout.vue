<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppWebFooter from '@/components/AppWebFooter.vue';
import AppWebHeader from '@/components/AppWebHeader.vue';
</script>

<template>
    <AppShell class="flex-col">
        <AppWebHeader />
        <AppContent>
            <slot />
        </AppContent>
        <AppWebFooter />
    </AppShell>
</template>
