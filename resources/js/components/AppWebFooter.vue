<script setup lang="ts">
import { Small } from '@/components/typography/texts';
</script>
<template>
    <footer class="bg-secondary text-secondary-foreground py-4 text-center">
        <Small>&copy; Tir Lavigny {{ new Date().getFullYear() }}</Small>
    </footer>
</template>
