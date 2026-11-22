<script setup lang="ts">
import AppEventCard from '@/components/AppEventCard.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import AppPostCard from '@/components/AppPostCard.vue';
import { H2 } from '@/components/typography/headings';
import { Large, List, Muted, P } from '@/components/typography/texts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import WebLayout from '@/layouts/WebLayout.vue';
import { contactHref, formatDate } from '@/lib/utils';
import { Event, Post } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, Mail, MapPin } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    events: Event[];
    posts: Post[];
    nextShootingEvent: Event | null;
}>();

const hasPosts = computed(() => props.posts.length > 0);
const hasEvents = computed(() => props.events.length > 0);

const shootingLinks = [
    {
        title: 'Essayer le tir',
        description: 'Initiation encadrée, sans matériel',
        route: 'shooting-initiation',
    },
    {
        title: 'Tirs militaires',
        description: 'Programme et dates',
        route: 'shootings',
    },
    {
        title: 'Devenir membre',
        description: 'Cotisation annuelle, sans engagement',
        route: 'become-member',
    },
    {
        title: 'Tir fédéral en campagne',
        description: 'Chaque mois de mai',
        route: 'shootings.field',
    },
];
</script>

<template>
    <WebLayout>
        <AppHeroSection title="Société de tir de Lavigny" image="/images/hero.webp">
            <template #description>
                <Large>Tirs militaires, tir fédéral et initiation au stand de 300 m.</Large>
            </template>
        </AppHeroSection>

        <section class="mt-96 pt-12">
            <Alert v-if="nextShootingEvent" class="mb-8">
                <CalendarDays class="size-4" />
                <AlertTitle>Prochain tir militaire</AlertTitle>
                <AlertDescription>
                    {{ formatDate(nextShootingEvent.start_datetime, 'dddd DD MMMM YYYY') }}.
                    <Link :href="route('shootings')" class="text-primary underline-offset-4 hover:underline">Voir les dates</Link>
                </AlertDescription>
            </Alert>
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 md:gap-12">
                <div class="flex flex-col gap-4">
                    <H2>Envie de tirer ?</H2>
                    <P>
                        Un stand de 300 m et 10 m, des moniteurs, et des séances de tir le jeudi. Que tu fasses tes tirs obligatoires, que tu veuilles
                        essayer ou que tu cherches une société où pratiquer à ton rythme, tu es au bon endroit.
                    </P>
                    <Button variant="link" size="lg" class="justify-start px-0">
                        <Link :href="route('events')" class="flex items-center"> <CalendarDays class="mr-2 h-4 w-4" /> Voir l’agenda </Link>
                    </Button>
                </div>
                <ul class="flex flex-col divide-y">
                    <li v-for="link in shootingLinks" :key="link.route">
                        <Link :href="route(link.route)" class="group flex items-center justify-between gap-4 py-4">
                            <div>
                                <p class="font-semibold">{{ link.title }}</p>
                                <Muted>{{ link.description }}</Muted>
                            </div>
                            <ArrowRight class="text-muted-foreground size-4 shrink-0 transition-transform group-hover:translate-x-1" />
                        </Link>
                    </li>
                </ul>
            </div>
        </section>

        <section data-testid="home-events-section" class="mt-12 flex flex-col gap-8" v-if="hasEvents">
            <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <H2>Prochain{{ events.length > 1 ? 's' : '' }} événement{{ events.length > 1 ? 's' : '' }}</H2>
                <Button variant="link" size="lg" class="px-0">
                    <Link :href="route('events')" class="flex items-center"> <CalendarDays class="mr-2 h-4 w-4" /> Tous les événements </Link>
                </Button>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <AppEventCard v-for="event in events" class="w-full" :key="event.id" :event="event" />
            </div>
        </section>

        <section v-if="hasPosts" class="mt-12 flex flex-col gap-8">
            <H2>Communication{{ posts.length > 1 ? 's' : '' }}</H2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <AppPostCard v-for="post in posts" class="w-full" :key="post.public_id" :post="post" />
            </div>
        </section>

        <section data-testid="home-range-section" class="mt-12 flex flex-col gap-8">
            <H2>Stand de tir</H2>
            <div class="grid w-full grid-cols-1 gap-4 md:grid-cols-2">
                <div class="flex items-center justify-center p-4">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3247.660305123527!2d6.4171308425140134!3d46.502018652577554!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x478c4979d60ac46f%3A0xc33a5df5bcc8807b!2sStand%20de%20tir%20de%20Lavigny!5e0!3m2!1sfr!2suk!4v1653593277476!5m2!1sfr!2suk"
                        height="400"
                        width="100%"
                        style="border: 0"
                        allowfullscreen="false"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
                <div class="flex flex-col items-start gap-4 p-4">
                    <Button variant="link" size="lg">
                        <a href="https://maps.app.goo.gl/N93Y7TnkXt5LjcXB6" rel="noreferrer" target="_blank" class="flex items-center">
                            <MapPin class="mr-2 h-4 w-4" /> GC2C+J3, 1175 Lavigny, Suisse
                        </a>
                    </Button>
                    <Button variant="link" size="lg">
                        <a :href="contactHref" class="flex items-center"> <Mail class="mr-2 h-4 w-4" /> Contactez-nous par mail </a>
                    </Button>
                    <div class="ml-6">
                        <P> Caractéristiques du stand : </P>
                        <List data-testid="range-characteristics-list" class="mt-0">
                            <li>Distance : 300m et 10m</li>
                            <li>Nombre de cibles : 6</li>
                            <li>Type de cibles : Sius Ascor</li>
                            <li>Commodités : Buvette intérieure, Toilettes H+F, Accès fauteuil roulant</li>
                        </List>
                    </div>
                </div>
            </div>
        </section>
    </WebLayout>
</template>

<style scoped></style>
