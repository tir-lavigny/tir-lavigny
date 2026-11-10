<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const buildTag = import.meta.env.VITE_BUILD_TAG || 'unknown';

defineProps({
    displayBuildTag: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-none font-semibold">Société de tir de Lavigny</span>
        <span v-if="displayBuildTag">{{ buildTag }}</span>
    </div>
</template>
