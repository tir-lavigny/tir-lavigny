<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import MapIframe from '@/components/MapIframe.vue';
import { H1, H3, H4 } from '@/components/typography/headings';
import { P, Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import { Event, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { PenBox, Trash2 } from 'lucide-vue-next';
import { marked } from 'marked';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    event: Event;
    content?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Événements',
        href: route('bo.events.index'),
    },
    {
        title: `${props.event.title}`,
        href: route('bo.events.show', { event: props.event.id }),
    },
];

const handleDelete = (id: number) => {
    if (!hasPermissionTo(user, 'delete event')) {
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
    <Head title="Événements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <div class="flex w-full flex-row justify-between">
                <H1> {{ event.title }} </H1>
                <div class="flex flex-row items-center gap-2">
                    <Tooltip v-if="hasPermissionTo(user, 'edit event')">
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
                    <Tooltip v-if="hasPermissionTo(user, 'delete event')">
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
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border p-4">
                    <H4>Événements</H4>
                    <div class="grid grid-cols-3 items-center justify-center gap-2">
                        <Small>Titre</Small>
                        <P class="col-span-2 !mt-0">{{ event.title }}</P>
                    </div>
                    <div class="grid grid-cols-3 items-center justify-center gap-2">
                        <Small>Début</Small>
                        <P class="col-span-2 !mt-0">{{ formatDate(event.start_datetime, 'dddd DD MMMM YYYY à HH:mm') }}</P>
                    </div>
                    <div class="grid grid-cols-3 items-center justify-center gap-2">
                        <Small>Fin</Small>
                        <P class="col-span-2 !mt-0" v-if="event.end_datetime">{{ formatDate(event.end_datetime, 'dddd DD MMMM YYYY à HH:mm') }}</P>
                        <P class="col-span-2 !mt-0" v-else>Non défini</P>
                    </div>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative flex aspect-video flex-col justify-between gap-6 overflow-hidden rounded-xl border p-4"
                >
                    <div class="flex flex-col gap-6">
                        <H4>Lieu</H4>

                        <div class="grid gap-2" v-if="event.location">
                            <P>{{ event.location.name }}</P>
                            <p class="text-muted-foreground text-sm">
                                {{ event.location.address }}
                            </p>
                        </div>
                        <div v-else>
                            <P>Non défini</P>
                        </div>
                    </div>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative flex aspect-video flex-col justify-between gap-6 overflow-hidden rounded-xl border p-4"
                >
                    <MapIframe
                        v-if="event.location"
                        :latitude="event.location.latitude"
                        :longitude="event.location.longitude"
                        :zoom="15"
                        height="100%"
                    />
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="flex flex-row items-center justify-between">
                    <H3>Contenu</H3>
                </div>
                <Separator />
                <div v-if="content" v-html="marked(content)" class="prose dark:prose-invert mt-4 w-full" />
                <div v-else>
                    <P class="text-muted-foreground"> Aucun contenu défini pour cet événement. </P>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
