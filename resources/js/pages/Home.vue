<script setup lang="ts">
import AppEventCard from '@/components/AppEventCard.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import AppPostCard from '@/components/AppPostCard.vue';
import { H2 } from '@/components/typography/headings';
import { Large, List, P } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import WebLayout from '@/layouts/WebLayout.vue';
import { Event, Post } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Mail, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    events: Event[];
    posts: Post[];
}>();

const hasPosts = computed(() => props.posts.length > 0);
const hasEvents = computed(() => props.events.length > 0);
</script>

<template>
    <Head title="Accueil" />

    <WebLayout>
        <AppHeroSection title="Société de tir de lavigny" image="/images/hero.jpg">
            <template #description> <Large> Tirs militaire, campagne et sportif </Large> </template>
        </AppHeroSection>
        <section class="mt-96 flex flex-col gap-8 pt-12" v-if="hasPosts">
            <H2>Communication{{ posts.length > 1 ? 's' : '' }}</H2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <AppPostCard v-for="post in posts" class="w-full" :key="post.public_id" :post="post" />
            </div>
        </section>
        <section class="mt-12 flex flex-col gap-8" v-if="hasEvents" :class="{ 'mt-96 pt-12': !hasPosts }">
            <H2>Prochain{{ events.length > 1 ? 's' : '' }} événement{{ events.length > 1 ? 's' : '' }}</H2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <AppEventCard v-for="event in events" class="w-full" :key="event.id" :event="event" />
            </div>
        </section>
        <section class="mt-12 flex flex-col gap-8" :class="{ 'mt-96 pt-12': !hasEvents && !hasPosts }">
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
                        <a
                            href="javascript:location='mailto:\u0063\u006f\u006e\u0074\u0061\u0063\u0074\u0040\u0074\u0069\u0072\u002d\u006c\u0061\u0076\u0069\u0067\u006e\u0079\u002e\u0063\u0068';void 0"
                            class="flex items-center"
                        >
                            <Mail class="mr-2 h-4 w-4" /> Contactez-nous par mail
                        </a>
                    </Button>
                    <div class="ml-6">
                        <P> Caractéristiques du stand : </P>
                        <List class="mt-0">
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
