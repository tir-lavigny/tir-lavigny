<script setup lang="ts">
import AppEventCard from '@/components/AppEventCard.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import { H2 } from '@/components/typography/headings';
import WebLayout from '@/layouts/WebLayout.vue';
import { Event } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    events: Event[];
}>();

console.log(props.events);
</script>

<template>
    <Head title="Accueil" />

    <WebLayout>
        <AppHeroSection title="Société de tir de lavigny" description="Tirs militaire, campagne et sportif" image="/images/hero.jpg" />
        <section class="mt-96 flex flex-col gap-8 pt-12">
            <H2>Prochains événements</H2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <AppEventCard v-for="event in events" class="w-full" :key="event.id" :event="event" />
            </div>
        </section>
    </WebLayout>
</template>

<style scoped></style>
