<script setup lang="ts">
import { Small } from '@/components/typography/texts';

const buildTag = import.meta.env.VITE_BUILD_TAG || 'unknown';
</script>
<template>
    <footer class="bg-secondary text-secondary-foreground py-4 text-center">
        <Small>&copy; Tir Lavigny {{ new Date().getFullYear() }} &middot; {{ buildTag }}</Small>
    </footer>
</template>
