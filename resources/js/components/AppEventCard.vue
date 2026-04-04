<script setup lang="ts">
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/utils';
import { Event } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Calendar, MapPin } from '@lucide/vue';

withDefaults(
    defineProps<{
        event: Event;
        short?: boolean;
    }>(),
    {
        short: false,
    },
);
</script>

<template>
    <Card class="justify-between" v-if="!short">
        <CardHeader>
            <CardTitle>
                {{ event.title }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Calendar class="text-muted-foreground h-4 w-4 min-w-4" />
                    <Small>
                        {{ formatDate(event.start_datetime, 'DD MMMM YYYY') }}
                        <template
                            v-if="
                                event.end_datetime &&
                                formatDate(event.start_datetime, 'DD MMMM YYYY') !== formatDate(event.end_datetime, 'DD MMMM YYYY')
                            "
                        >
                            - {{ formatDate(event.end_datetime, 'DD MMMM YYYY') }}
                        </template>
                    </Small>
                    <Small>
                        {{ formatDate(event.start_datetime, 'HH:mm') }}
                        <template v-if="event.end_datetime && formatDate(event.start_datetime, 'HH:mm') !== formatDate(event.end_datetime, 'HH:mm')">
                            - {{ formatDate(event.end_datetime, 'HH:mm') }}
                        </template>
                    </Small>
                </div>
                <div class="flex items-center gap-2">
                    <MapPin class="text-muted-foreground h-4 w-4 min-w-4" />
                    <Small class="text-primary" v-if="event.location">
                        {{ event.location.name }}
                        <span class="text-foreground"> - {{ event.location.address }} </span>
                    </Small>
                    <Small v-else> Pas de lieu défini </Small>
                </div>
            </div>
        </CardContent>
        <CardFooter>
            <Button variant="secondary" size="sm">
                <Link class="flex items-center gap-2" :href="route('events.show', event.id)"> Voir l'événement <ArrowRight class="h-4 w-4" /> </Link>
            </Button>
        </CardFooter>
    </Card>
    <Card class="justify-between" v-else>
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle>
                {{ event.title }}
            </CardTitle>
            <Button variant="secondary" size="icon">
                <Link class="flex items-center gap-2" :href="route('events.show', event.id)"> <ArrowRight class="h-4 w-4" /> </Link>
            </Button>
        </CardHeader>
        <CardContent>
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <Calendar class="text-muted-foreground h-4 w-4 min-w-4" />
                    <Small>
                        {{ formatDate(event.start_datetime, 'DD MMMM YYYY') }}
                        <template
                            v-if="
                                event.end_datetime &&
                                formatDate(event.start_datetime, 'DD MMMM YYYY') !== formatDate(event.end_datetime, 'DD MMMM YYYY')
                            "
                        >
                            - {{ formatDate(event.end_datetime, 'DD MMMM YYYY') }}
                        </template>
                    </Small>
                    <Small>
                        {{ formatDate(event.start_datetime, 'HH:mm') }}
                        <template v-if="event.end_datetime && formatDate(event.start_datetime, 'HH:mm') !== formatDate(event.end_datetime, 'HH:mm')">
                            - {{ formatDate(event.end_datetime, 'HH:mm') }}
                        </template>
                    </Small>
                </div>
                <div class="flex items-center gap-2">
                    <MapPin class="text-muted-foreground h-4 w-4 min-w-4" />
                    <Small v-if="event.location">
                        {{ event.location.name }}
                    </Small>
                    <Small v-else> Pas de lieu défini </Small>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

<style scoped></style>
