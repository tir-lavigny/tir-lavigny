<script setup lang="ts">
import AppEventCalendar from '@/components/AppEventCalendar.vue';
import AppEventCalendarCard from '@/components/AppEventCalendarCard.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import { H2, H3 } from '@/components/typography/headings';
import { Large, P } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import WebLayout from '@/layouts/WebLayout.vue';
import { Event } from '@/types';
import { Head } from '@inertiajs/vue3';
import { createReusableTemplate, formatDate, useMediaQuery } from '@vueuse/core';
import { ListFilter } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const isMobile = useMediaQuery('(max-width: 768px)');

const props = defineProps<{
    events?: Event[];
}>();

const DEFAULT_FILTER = 'future';
const filter = ref<'all' | 'passed' | 'future'>(DEFAULT_FILTER);

const filteredEvents = computed(() => {
    if (!props.events) return [];
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Reset time to the start of the day

    switch (filter.value) {
        case 'passed':
            return props.events.filter((event) => {
                const eventDate = new Date(event.start_datetime);
                eventDate.setHours(0, 0, 0, 0);
                return eventDate < today;
            });
        case 'future':
            return props.events.filter((event) => {
                const eventDate = new Date(event.start_datetime);
                eventDate.setHours(0, 0, 0, 0);
                return eventDate >= today;
            });
        default:
            return props.events;
    }
});

const groupedEvents = computed(() => {
    return filteredEvents.value.reduce(
        (groups, event) => {
            const date = event.start_datetime.split('T')[0];
            if (!groups[date]) groups[date] = [];
            groups[date].push(event);
            return groups;
        },
        {} as Record<string, Event[]>,
    );
});

const [DefineTemplate, ReuseTemplate] = createReusableTemplate();
</script>

<template>
    <Head title="Événements" />

    <DefineTemplate>
        <H2 class="!pb-0">Saison {{ new Date().getFullYear() }}</H2>
        <DropdownMenu>
            <DropdownMenuTrigger>
                <Button>
                    <ListFilter class="h-4 w-4" /> Filtrer
                    <span v-if="filter !== DEFAULT_FILTER">(1)</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                <DropdownMenuItem @click="filter = 'all'" :class="{ 'font-bold': filter === 'all' }">Tous les événements</DropdownMenuItem>
                <DropdownMenuItem @click="filter = 'passed'" :class="{ 'font-bold': filter === 'passed' }">Événements passés</DropdownMenuItem>
                <DropdownMenuItem @click="filter = 'future'" :class="{ 'font-bold': filter === 'future' }">Événements futurs</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </DefineTemplate>

    <WebLayout>
        <AppHeroSection title="Événements" image="/images/hero.jpg">
            <template #description> <Large> Prochains événements, tirs, etc. </Large> </template>
        </AppHeroSection>

        <section v-if="!events" class="mt-96 flex flex-col gap-8 py-12">
            <P>Pas d'événements à afficher</P>
        </section>
        <template v-else>
            <section v-if="isMobile" class="mt-96 flex flex-col gap-8 py-12">
                <div class="flex flex-row items-center justify-between">
                    <ReuseTemplate />
                </div>

                <div v-for="(events, date) in groupedEvents" :key="date" class="mx-auto w-full max-w-4xl">
                    <H3 :class="{ 'text-primary': new Date(date).toDateString() === new Date().toDateString() }">
                        {{ formatDate(new Date(date), 'ddd DD MMMM YYYY') }}
                    </H3>
                    <AppEventCalendarCard v-for="event in events" :key="event.id" :event="event" class="mt-4" />
                </div>
                <div v-if="filteredEvents.length === 0" class="text-center">
                    <P>Aucun événement trouvé pour ce filtre.</P>
                </div>
            </section>

            <section v-else class="mt-96 pt-12">
                <div class="mb-6 flex flex-row items-center gap-6">
                    <ReuseTemplate />
                </div>
                <AppEventCalendar :grouped-events="groupedEvents" />
            </section>
        </template>
    </WebLayout>
</template>

<style scoped></style>
