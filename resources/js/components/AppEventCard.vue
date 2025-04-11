<script setup lang="ts">
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Event } from '@/types';
import { Link } from '@inertiajs/vue3';
import { formatDate } from '@vueuse/core';
import { ArrowRight, Calendar, MapPin } from 'lucide-vue-next';

defineProps<{
    event: Event;
}>();
</script>

<template>
    <Card class="justify-between">
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
                        {{ formatDate(new Date(event.start_datetime), 'DD MMMM YYYY') }}
                        <template
                            v-if="
                                event.end_datetime &&
                                formatDate(new Date(event.start_datetime), 'DD MMMM YYYY') !==
                                    formatDate(new Date(event.end_datetime), 'DD MMMM YYYY')
                            "
                        >
                            - {{ formatDate(new Date(event.end_datetime), 'DD MMMM YYYY') }}
                        </template>
                    </Small>
                    <Small>
                        {{ formatDate(new Date(event.start_datetime), 'HH:mm') }}
                        <template
                            v-if="
                                event.end_datetime &&
                                formatDate(new Date(event.start_datetime), 'HH:mm') !== formatDate(new Date(event.end_datetime), 'HH:mm')
                            "
                        >
                            - {{ formatDate(new Date(event.end_datetime), 'HH:mm') }}
                        </template>
                    </Small>
                </div>
                <div class="flex items-center gap-2">
                    <MapPin class="text-muted-foreground h-4 w-4 min-w-4" />
                    <Small class="text-primary" v-if="event.location">
                        {{ event.location.name }} -
                        <span class="text-foreground">
                            {{ event.location.address }}
                        </span>
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
</template>

<style scoped></style>
