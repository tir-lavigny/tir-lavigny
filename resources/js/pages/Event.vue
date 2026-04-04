<script setup lang="ts">
import AppEventAdress from '@/components/AppEventAdress.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import { Muted, P } from '@/components/typography/texts';
import WebLayout from '@/layouts/WebLayout.vue';
import { formatDate } from '@/lib/utils';
import { Event } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Calendar, Clock, MapPin } from '@lucide/vue';
import { marked } from 'marked';

defineProps<{
    event: Event;
    content?: string;
}>();
</script>

<template>
    <Head title="Événement" />

    <WebLayout>
        <AppHeroSection :title="event.title" image="/images/hero.jpg">
            <template #description>
                <div class="flex flex-col items-center justify-center gap-2 sm:flex-row sm:gap-4">
                    <div class="flex flex-row items-center gap-2">
                        <Calendar class="h-4 w-4 min-w-4" />
                        <P class="!mt-0">
                            {{ formatDate(event.start_datetime, 'ddd DD MMMM YYYY') }}
                            <template
                                v-if="
                                    event.end_datetime &&
                                    formatDate(event.start_datetime, 'DD MMMM YYYY') !== formatDate(event.end_datetime, 'DD MMMM YYYY')
                                "
                            >
                                - {{ formatDate(event.end_datetime, 'ddd DD MMMM YYYY') }}
                            </template>
                        </P>
                    </div>
                    <Muted class="hidden sm:block">|</Muted>
                    <div class="flex flex-row items-center gap-2">
                        <Clock class="h-4 w-4 min-w-4" />
                        <P class="!mt-0">
                            {{ formatDate(event.start_datetime, 'HH:mm') }}
                            <template
                                v-if="event.end_datetime && formatDate(event.start_datetime, 'HH:mm') !== formatDate(event.end_datetime, 'HH:mm')"
                            >
                                - {{ formatDate(event.end_datetime, 'HH:mm') }}
                            </template>
                        </P>
                    </div>
                    <template v-if="event.location">
                        <Muted class="hidden sm:block">|</Muted>
                        <div class="flex flex-row items-center gap-2">
                            <MapPin class="h-4 w-4 min-w-4" />
                            <P class="!mt-0">
                                {{ event.location.name }}
                            </P>
                        </div>
                    </template>
                </div>
            </template>
        </AppHeroSection>
        <section class="mt-96 flex flex-col gap-8 py-12">
            <div v-if="content" v-html="marked(content)" class="prose dark:prose-invert xl:prose-xl max-w-full"></div>
            <div v-else>
                <P>Pas de contenu à afficher</P>
            </div>
        </section>
        <section class="flex flex-col gap-8 py-12">
            <AppEventAdress v-if="event.location" :location="event.location" />
        </section>
    </WebLayout>
</template>

<style scoped></style>
