<script setup lang="ts">
import { H1 } from '@/components/typography/headings';
import { P } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        status?: number;
    }>(),
    {
        status: 404,
    },
);

const title = computed(() => {
    return (
        {
            503: '503: Service indisponible',
            500: '500: Erreur serveur',
            404: '404: Page non trouvée',
            403: '403: Interdit',
        }[props.status] || 'Erreur inconnue'
    );
});

const description = computed(() => {
    return (
        {
            503: 'Désolé, nous effectuons une maintenance. Veuillez revenir bientôt.',
            500: "Oups, quelque chose s'est mal passé sur nos serveurs.",
            404: 'Désolé, la page que vous recherchez est introuvable.',
            403: "Désolé, vous n'êtes pas autorisé à accéder à cette page.",
        }[props.status] || "Une erreur inconnue s'est produite."
    );
});
</script>

<template>
    <div class="bg-background flex min-h-[100dvh] flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-md text-center">
            <H1>{{ title }}</H1>
            <P>{{ description }}</P>
            <div class="mt-6">
                <Link :href="route('home')"> <Button class="w-full"> Retour à l'accueil </Button> </Link>
            </div>
        </div>
    </div>
</template>
