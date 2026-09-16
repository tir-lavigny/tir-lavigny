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
import { ListFilter } from '@lucide/vue';
import { createReusableTemplate, formatDate, useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
import { parseDateOnly } from '@/lib/utils';

const isMobile = useMediaQuery('(max-width: 768px)');

const props = defineProps<{
    events: Event[];
}>();

const DEFAULT_FILTER = 'future';
const filter = ref<'all' | 'passed' | 'future'>(DEFAULT_FILTER);

const filteredEvents = computed(() => {
    if (!props.events.length) return [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    switch (filter.value) {
        case 'passed':
            return props.events.filter((event) => {
                const eventDate = new Date(event.start_datetime);
                const localDate = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());
                return localDate < today;
            });
        case 'future':
            return props.events.filter((event) => {
                const eventDate = new Date(event.start_datetime);
                const localDate = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());
                return localDate >= today;
            });
        default:
            return props.events;
    }
});

const groupedEvents = computed(() => {
    return filteredEvents.value.reduce(
        (groups, event) => {
            const d = new Date(event.start_datetime);
            const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
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
                <Button data-testid="events-filter-trigger">
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

        <section v-if="isMobile" class="mt-96 flex flex-col gap-8 py-12">
            <div class="flex flex-row items-center justify-between">
                <ReuseTemplate />
            </div>

            <P v-if="!events.length" data-testid="no-events-message" class="text-center">Aucun événement pour l'instant.</P>
            <template v-else>
                <div v-for="(events, date) in groupedEvents" :key="date" class="mx-auto w-full max-w-4xl">
                    <H3 :class="{ 'text-primary': parseDateOnly(date).toDateString() === new Date().toDateString() }">
                        {{ formatDate(parseDateOnly(date), 'ddd DD MMMM YYYY') }}
                    </H3>
                    <AppEventCalendarCard v-for="event in events" :key="event.id" :event="event" class="mt-4" />
                </div>
                <div v-if="filteredEvents.length === 0" class="text-center">
                    <P>Aucun événement trouvé pour ce filtre.</P>
                </div>
            </template>
        </section>

        <section v-else class="mt-96 pt-12">
            <div class="mb-6 flex flex-row items-center gap-6">
                <ReuseTemplate />
            </div>

            <AppEventCalendar :grouped-events="groupedEvents" :has-events="!!events.length" />
        </section>
    </WebLayout>
</template>

<style scoped></style>
