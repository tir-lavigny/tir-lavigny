<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDate } from '@/lib/utils';
import { Event } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Calendar, MapPin, PenBox, Trash2 } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const props = withDefaults(
    defineProps<{
        event: Event;
        actions?: string[];
    }>(),
    {
        actions: () => ['edit', 'delete'],
    },
);

const handleDelete = (id: number) => {
    if (!props.actions.includes('delete')) {
        toast.error("Vous n'avez pas le droit de supprimer un événement");
        return;
    }

    const form = useForm({
        id,
    });

    form.delete(route('bo.events.destroy', id), {
        onSuccess: () => {
            toast.success('Événement supprimé avec succès');
            form.reset();
        },
        onError: () => {
            toast.error("Une erreur est survenue lors de la suppression de l'événement");
            form.reset();
        },
    });
};
</script>

<template>
    <Card class="justify-between">
        <CardHeader class="flex flex-col items-center justify-between sm:flex-row">
            <CardTitle>
                {{ event.title }}
            </CardTitle>
            <div class="flex flex-row items-center gap-2">
                <Tooltip v-if="actions.includes('edit')">
                    <TooltipTrigger>
                        <Link class="flex items-center gap-2" :href="route('bo.events.edit', event.id)">
                            <Button size="icon" variant="secondary">
                                <PenBox />
                            </Button>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Modifier l'événement</p>
                    </TooltipContent>
                </Tooltip>
                <Tooltip v-if="actions.includes('delete')">
                    <TooltipTrigger>
                        <AppAlertDialog
                            title="Êtes-vous absolument sûr ?"
                            description="Cette action est irréversible. Cela supprimera définitivement cet événement."
                            cancelText="Annuler"
                        >
                            <template #trigger>
                                <Button size="icon" variant="destructive">
                                    <Trash2 />
                                </Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleDelete(event.id)">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Supprimer l'événement</p>
                    </TooltipContent>
                </Tooltip>
            </div>
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
                <Link class="flex items-center gap-2" :href="route('bo.events.show', event.id)">
                    Voir l'événement <ArrowRight class="h-4 w-4" />
                </Link>
            </Button>
        </CardFooter>
    </Card>
</template>

<style scoped></style>
