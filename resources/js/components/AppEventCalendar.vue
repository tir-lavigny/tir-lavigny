<script setup lang="ts">
import AppEventCalendarCard from '@/components/AppEventCalendarCard.vue';
import { H3 } from '@/components/typography/headings';
import { P } from '@/components/typography/texts';
import { Calendar } from '@/components/ui/calendar';
import { ScrollArea } from '@/components/ui/scroll-area';
import { formatDate } from '@/lib/utils';
import { Event } from '@/types';
import { type DateValue, getLocalTimeZone, today } from '@internationalized/date';
import { type Ref, ref, watchEffect } from 'vue';

const props = defineProps<{
    groupedEvents: Record<string, Event[]>;
    hasEvents: boolean;
}>();

const value = ref(today(getLocalTimeZone())) as Ref<DateValue>;

watchEffect(() => {
    const selectedDate = value.value.toString();
    let element = document.getElementById(`date-${selectedDate}`);

    if (!element) {
        const dates = Object.keys(props.groupedEvents).sort();
        const closestDate = dates.find((date) => new Date(date) >= new Date(selectedDate)) || dates[dates.length - 1];
        element = document.getElementById(`date-${closestDate}`);
    }

    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }
});
</script>

<template>
    <div class="mb-4 grid grid-cols-3 gap-4">
        <ScrollArea class="col-span-2 max-h-[80dvh]">
            <template v-if="Object.keys(groupedEvents).length === 0">
                <P class="text-center"> {{ !hasEvents ? "Aucun événement pour l'instant." : 'Aucun événement ne correspond à ce filtre.' }} </P>
            </template>
            <template v-else>
                <div v-for="(events, date) in groupedEvents" :key="date" class="mx-auto w-full max-w-4xl pr-4">
                    <H3
                        :id="`date-${date}`"
                        :class="{ 'text-primary': new Date(date).toDateString() === new Date().toDateString() }"
                        class="bg-background sticky top-0 z-10 py-4"
                    >
                        {{ formatDate(date, 'ddd DD MMMM YYYY') }}
                    </H3>
                    <AppEventCalendarCard v-for="event in events" :key="event.id" :event="event" class="mt-4" />
                </div>
            </template>
        </ScrollArea>
        <div class="col-span-1 flex justify-end">
            <div class="w-fit">
                <Calendar v-model="value" weekday-format="short" locale="fr-CH" :fixedWeeks="true" class="rounded-md border" />
            </div>
        </div>
    </div>
</template>
