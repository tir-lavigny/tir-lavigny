<script setup lang="ts">
import AppHeroSection from '@/components/AppHeroSection.vue';
import { H2 } from '@/components/typography/headings';
import { Card } from '@/components/ui/card';
import WebLayout from '@/layouts/WebLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Accueil" />

    <WebLayout>
        <AppHeroSection title="Société de tir de lavigny" description="Tirs militaire, campagne et sportif" image="/images/hero.jpg" />
        <section class="mt-96 pt-12">
            <H2>Prochains événements</H2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card class="w-full">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold">Événement 1</h3>
                        <p class="mt-2 text-gray-600">Description de l'événement 1.</p>
                    </div>
                </Card>
            </div>
        </section>
    </WebLayout>
</template>

<style scoped></style>
