<script setup lang="ts">
import AppEventCard from '@/components/AppEventCard.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import TextLink from '@/components/TextLink.vue';
import { H2 } from '@/components/typography/headings';
import { List, P } from '@/components/typography/texts';
import WebLayout from '@/layouts/WebLayout.vue';
import { Event } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    events: Event[];
}>();
</script>

<template>
    <Head title="Tirs militaires" />

    <WebLayout>
        <AppHeroSection title="Tirs militaires" image="/images/hero.jpg"></AppHeroSection>
        <section class="mt-96 gap-8 py-12">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="flex flex-col gap-4 md:col-span-2">
                    <div class="text-pretty">
                        <H2>Venez effectuer vos tirs obligatoires</H2>
                        <P class="text-muted-foreground text-sm leading-none font-medium md:hidden">
                            Consultez les <TextLink href="#next-dates" class="text-primary !decoration-primary">prochaines dates</TextLink> pour
                            planifier votre tir.
                        </P>
                        <P>
                            Notre société organise chaque année des séances de tir afin d'offrir la possibilité aux tireurs astreints et non
                            astreints, d'effectuer le programme obligatoire à 300 mètres au stand de Lavigny.
                        </P>
                        <P> Le tir est gratuit, sauf la munition d'exercice, CHF 2.00 pour 3 coups. </P>
                        <P class="font-bold"> Important: La munition non tirée, doit être rendue à la fin du tir. </P>
                        <img
                            src="/images/tirMilitaire.jpg"
                            alt="Tirs militaires"
                            class="mt-4 h-96 w-full rounded-lg object-cover object-center md:col-span-2 md:row-span-2"
                        />
                    </div>
                    <div class="text-pretty">
                        <H2>Programme</H2>
                        <P>
                            Les tirs obligatoires sont considérés comme réussis lorsque le militaire astreint au tir a obtenu un
                            <span class="font-bold">minimum de 42 points</span>
                            et n'a pas obtenu <span class="font-bold">plus de trois zéros</span>.
                        </P>
                        <P> Pour accomplir ses tirs obligatoires, il faut prendre avec soi&nbsp;: </P>
                        <List class="mt-0">
                            <li>Le courrier de convocation avec les étiquettes autocollantes</li>
                            <li>Le livret de service</li>
                            <li>Le livret de tir ou le livret de performances militaire</li>
                            <li>La pièce d’identité officielle</li>
                            <li>Son L’arme de service personnelle avec la trousse de nettoyage</li>
                            <li>La protection d’ouïe personnelle</li>
                        </List>
                    </div>
                </div>
                <div class="md:col-span-1">
                    <H2 id="next-dates">Prochaines dates</H2>
                    <div class="mt-6 flex w-full flex-col gap-2">
                        <div v-if="events.length" class="flex w-full flex-col gap-2">
                            <AppEventCard v-for="event in events" :key="event.id" :event="event" :short="true" />
                        </div>
                        <template v-else>
                            <P v-if="new Date().getTime() < new Date('2025-08-31').getTime()"> Aucune date disponible pour le moment. </P>
                            <P v-else> Plus aucune date pour la saison {{ new Date().getFullYear() }}. </P>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </WebLayout>
</template>

<style scoped></style>
