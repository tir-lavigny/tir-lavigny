<script setup lang="ts">
import AppHeroSection from '@/components/AppHeroSection.vue';
import TextLink from '@/components/TextLink.vue';
import { H2 } from '@/components/typography/headings';
import { Large, Muted, P } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WebLayout from '@/layouts/WebLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, CircleCheck, Mail, ShieldCheck, Wallet } from '@lucide/vue';

const facts = [
    {
        icon: CalendarDays,
        title: 'Les jeudis',
        description: 'Séances de tir',
    },
    {
        icon: CircleCheck,
        title: 'Sans engagement',
        description: 'Tu participes quand tu veux',
    },
    {
        icon: Wallet,
        title: 'CHF 100.–',
        description: 'Par année, crédit inclus',
    },
];

const appointments = [
    {
        timing: 'Début d’année',
        title: 'L’assemblée générale',
        description:
            'Bilan de l’année écoulée, présentation des comptes, projets à venir et échanges entre membres. Un moment important pour participer à la vie de la société.',
    },
    {
        timing: 'Dernier jour de tir',
        title: 'Le tir de clôture',
        description:
            'Un samedi, pour marquer la fin de la saison. Nous nous retrouvons une dernière fois au stand pour terminer l’année dans une ambiance sportive et conviviale.',
    },
    {
        timing: 'Fin d’année',
        title: 'La soirée palmarès',
        description:
            'La remise des prix aux tireurs ayant participé aux différents tirs de l’année. L’occasion de revenir sur les moments forts et de partager une soirée entre membres.',
    },
];
</script>

<template>
    <Head title="Devenir membre" />

    <WebLayout>
        <AppHeroSection title="Devenir membre" image="/images/hero.jpg">
            <template #description>
                <Large>Le plaisir du tir, sans contraintes !</Large>
            </template>
        </AppHeroSection>

        <section class="mt-96 pt-12">
            <div class="mx-auto grid w-full max-w-3xl grid-cols-1 gap-3 px-4 sm:grid-cols-3">
                <div
                    v-for="fact in facts"
                    :key="fact.title"
                    class="bg-card flex items-center gap-3 rounded-xl border p-4 sm:flex-col sm:gap-2 sm:text-center"
                >
                    <component :is="fact.icon" class="text-primary size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">{{ fact.title }}</p>
                        <Muted>{{ fact.description }}</Muted>
                    </div>
                </div>
            </div>
        </section>

        <article class="mx-auto flex w-full max-w-3xl flex-col gap-12 px-4 py-12">
            <section class="text-pretty">
                <P>
                    Tu souhaites pratiquer le tir sportif dans une ambiance conviviale, sans devoir t’engager dans un programme chargé ni participer à
                    toutes les activités ? Alors, la Société de tir de Lavigny est faite pour toi !
                </P>
                <P>
                    Notre société te permet de pratiquer le tir à ton rythme, selon tes envies et tes disponibilités. Que tu sois un tireur
                    expérimenté ou que tu souhaites simplement découvrir cette discipline durant ton temps libre, tu es le bienvenu parmi nous.
                </P>
            </section>

            <section id="seances" class="scroll-mt-24 text-pretty">
                <H2>Tu tires quand tu en as envie</H2>
                <P>
                    <strong>Les séances de tir ont lieu les jeudis.</strong> Pour connaître les dates précises, les horaires et les éventuelles
                    particularités, consulte <TextLink :href="route('events')" class="text-primary decoration-primary!">l’agenda</TextLink> de notre
                    site internet.
                </P>
                <P>
                    Il n’y a aucune obligation de participer à chaque journée de tir ni de venir régulièrement au stand. Que tu viennes souvent ou
                    seulement de temps en temps, tu gardes toute ta liberté.
                </P>
                <P>
                    Notre philosophie est simple : se faire plaisir, progresser à son rythme et partager de bons moments autour du tir sportif, sans
                    contraintes inutiles.
                </P>
            </section>

            <section id="exterieurs" class="scroll-mt-24 text-pretty">
                <H2>Des tirs extérieurs pour découvrir de nouveaux horizons</H2>
                <P>
                    La Société de tir de Lavigny participe également à plusieurs tirs extérieurs organisés dans la région et ailleurs, notamment à
                    Villars-sous-Yens, Duillier, aux Mosses et dans d’autres localités.
                </P>
                <P>
                    Ces rencontres sont une belle occasion de découvrir de nouveaux stands, de varier les expériences de tir et de rencontrer des
                    tireurs d’autres sociétés. C’est aussi l’opportunité de partager des moments conviviaux avec les autres membres.
                </P>
                <P>
                    La participation à ces tirs extérieurs reste entièrement facultative. Tu choisis librement les événements auxquels tu souhaites
                    participer.
                </P>
            </section>

            <section id="week-end" class="scroll-mt-24 text-pretty">
                <H2>Un week-end de tir chaque année</H2>
                <P>
                    Une fois par année, nous organisons un week-end de tir dans un autre canton suisse. Nous essayons de changer de destination d’une
                    année à l’autre afin de découvrir différentes régions, de nouveaux stands et d’autres traditions de tir.
                </P>
                <P>
                    Ce week-end est l’occasion de sortir de nos habitudes, de vivre de nouvelles expériences et surtout de passer de bons moments
                    ensemble. Chacun est libre d’y participer ou non : le programme de la société est là pour te proposer des activités, pas pour
                    t’imposer des obligations.
                </P>
            </section>

            <section id="rendez-vous" class="scroll-mt-24 text-pretty">
                <H2>Les rendez-vous incontournables</H2>
                <P>En plus des séances de tir habituelles, trois rendez-vous rythment la vie de notre société au cours de l’année.</P>
                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card v-for="appointment in appointments" :key="appointment.title" class="gap-4">
                        <CardHeader class="gap-2">
                            <Badge variant="secondary" class="mb-1">{{ appointment.timing }}</Badge>
                            <CardTitle class="text-lg leading-tight">{{ appointment.title }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-muted-foreground text-sm leading-relaxed">{{ appointment.description }}</p>
                        </CardContent>
                    </Card>
                </div>
                <Muted class="mt-4">
                    Les dates de ces rendez-vous sont communiquées dans
                    <TextLink :href="route('events')" class="text-primary decoration-primary!">l’agenda</TextLink>.
                </Muted>
            </section>

            <section id="arme" class="scroll-mt-24 text-pretty">
                <H2>Tu ne possèdes pas d’arme ?</H2>
                <P>
                    Tu aimerais pratiquer le tir, mais tu ne possèdes pas encore ta propre arme ? Ce n’est pas un problème : la société peut
                    s’arranger pour t’en mettre une à disposition.
                </P>
                <Card class="mt-6 gap-4">
                    <CardContent class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="bg-background flex size-10 shrink-0 items-center justify-center rounded-full border">
                            <ShieldCheck class="text-primary size-5" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">Une arme mise à disposition</p>
                                <Badge variant="secondary">Sur demande</Badge>
                            </div>
                            <p class="text-muted-foreground text-sm leading-relaxed">
                                Son utilisation se fait sous la supervision d’un moniteur de tir, qui t’accompagne et veille au respect des règles de
                                sécurité. Tu peux ainsi découvrir le tir ou pratiquer sans devoir investir immédiatement dans ton propre matériel.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section id="cotisation" class="scroll-mt-24 text-pretty">
                <H2>Une cotisation accessible</H2>
                <P> Nous souhaitons que la pratique du tir reste accessible et que chacun puisse profiter de notre société selon ses envies. </P>
                <Card class="mt-6">
                    <CardHeader>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <CardTitle class="text-xl">Cotisation annuelle</CardTitle>
                            <Badge>CHF 100.–</Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span>Crédit de tir inclus</span>
                            <span class="font-semibold whitespace-nowrap">CHF 50.–</span>
                        </div>
                        <Muted>
                            La cotisation annuelle de CHF 100.– comprend un crédit de tir de CHF 50.–, utilisable pour tes séances. Une arme peut
                            également être mise à ta disposition sur demande.
                        </Muted>
                    </CardContent>
                </Card>
            </section>

            <section id="rejoindre" class="scroll-mt-24 text-pretty">
                <H2>Envie de nous rejoindre ?</H2>
                <P>
                    Tu recherches une société de tir conviviale, où tu peux pratiquer à ton rythme, rencontrer d’autres passionnés et participer aux
                    activités qui t’intéressent, sans obligation de présence ?
                </P>
                <P>
                    Que tu possèdes déjà une arme ou que tu souhaites simplement découvrir le tir sportif, nous serons heureux de t’accueillir parmi
                    nous.
                </P>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <Button as-child size="lg">
                        <a
                            href="javascript:location='mailto:\u0063\u006f\u006e\u0074\u0061\u0063\u0074\u0040\u0074\u0069\u0072\u002d\u006c\u0061\u0076\u0069\u0067\u006e\u0079\u002e\u0063\u0068';void 0"
                        >
                            <Mail class="size-4" />
                            Nous contacter
                        </a>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <Link :href="route('events')">
                            <CalendarDays class="size-4" />
                            Consulter l’agenda
                        </Link>
                    </Button>
                </div>
                <p class="mt-8 text-center font-semibold text-balance">
                    La Société de tir de Lavigny : le plaisir de tirer, la liberté de participer et la convivialité avant tout !
                </p>
            </section>
        </article>
    </WebLayout>
</template>
