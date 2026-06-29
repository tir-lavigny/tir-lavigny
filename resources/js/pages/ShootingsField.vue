<script setup lang="ts">
import AppEventCard from '@/components/AppEventCard.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import TextLink from '@/components/TextLink.vue';
import { H2 } from '@/components/typography/headings';
import { OrderedList, P } from '@/components/typography/texts';
import WebLayout from '@/layouts/WebLayout.vue';
import { Event } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    events: Event[];
}>();
</script>

<template>
    <Head title="Tir fédéral en campagne" />

    <WebLayout>
        <AppHeroSection title="Tir fédéral en campagne" image="/images/hero.jpg"></AppHeroSection>
        <section class="mt-96 gap-8 py-12">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="flex flex-col gap-4 md:col-span-2">
                    <div class="text-pretty">
                        <H2>Venez participer au tir fédéral en campagne</H2>
                        <P class="text-muted-foreground text-sm leading-none font-medium md:hidden">
                            Consultez les <TextLink href="#next-dates" class="text-primary decoration-primary!">prochaines dates</TextLink> pour
                            planifier votre tir.
                        </P>
                        <P>
                            Le tir fédéral est la plus grande fête de tir au monde. Pendant tout un week-end au mois de mai, l'une des plus anciennes
                            traditions de tir est célébrée dans une ambiance conviviale et sportive à l'échelle nationale. Une fête pour les tireurs
                            et tous ceux qui aiment le tir ou souhaitent s'y essayer.
                        </P>
                        <P>
                            Sont autorisés à participer les tireuses et tireurs qui atteignent l’âge de 10 ans au cours de l’année de la compétition
                            (principe de l’année de naissance).
                        </P>
                        <P>
                            Chaque année, le tir fédéral en campagne se déroule dans un stand différent. Plusieurs sociétés de la région se retrouvent
                            ensemble pour tirer, ce qui favorise la convivialité et les échanges entre tireurs. Il est donc tout à fait possible qu'un
                            tir en campagne ait lieu à Etoy, par exemple, tout en y participant sous les couleurs de la Société de tir de Lavigny.
                        </P>
                        <img
                            src="/images/tirCampagne.webp"
                            alt="Tir fédéral en campagne"
                            class="mt-4 h-96 w-full rounded-lg object-cover object-center md:col-span-2 md:row-span-2"
                        />
                    </div>
                    <div class="text-pretty">
                        <H2>Programme</H2>
                        <P>
                            Les tirs sont organisés en trois séries de tir, chacune avec un temps imparti. Le tir est effectué à 300 mètres sur des
                            cibles B.
                        </P>
                        <OrderedList class="mt-0">
                            <li>Six coups sont tirés en 6 minutes et affiché un par un.</li>
                            <li>
                                Deux séries de trois coups chacune sont tirées en 60 secondes chacune. L'affichage a lieu après chaque série de trois
                                coups.
                            </li>
                            <li>Six coups sont tirés en 60 secondes. Affichage a lieu à la fin.</li>
                        </OrderedList>
                    </div>
                    <div class="text-pretty">
                        <H2>Plus d'informations</H2>
                        <P>
                            Pour toute question concernant le tir fédéral en campagne, n'hésitez pas à nous contacter
                            <a
                                href="javascript:location='mailto:\u0063\u006f\u006e\u0074\u0061\u0063\u0074\u0040\u0074\u0069\u0072\u002d\u006c\u0061\u0076\u0069\u0067\u006e\u0079\u002e\u0063\u0068';void 0"
                                class="text-primary decoration-primary!"
                            >
                                par mail.
                            </a>
                        </P>
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
