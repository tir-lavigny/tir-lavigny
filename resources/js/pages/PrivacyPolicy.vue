<script setup lang="ts">
import { H1 } from '@/components/typography/headings';
import WebLayout from '@/layouts/WebLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Protection des données" />

    <WebLayout>
        <section class="flex flex-col gap-8 py-12">
            <H1>Déclaration de protection des données</H1>
            <div class="prose dark:prose-invert xl:prose-xl max-w-full"></div>
        </section>
    </WebLayout>
</template>
