<script setup lang="ts">
import AppBreadcrumbs from '@/components/AppBreadcrumbs.vue';
import AppHeroSection from '@/components/AppHeroSection.vue';
import TextLink from '@/components/TextLink.vue';
import { H2 } from '@/components/typography/headings';
import { Large, Muted, P } from '@/components/typography/texts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WebLayout from '@/layouts/WebLayout.vue';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, CircleCheck, Clock, Crosshair, Info, Mail, ShieldCheck, Target, Users, Utensils } from '@lucide/vue';

const initiationOptions = [
    {
        title: 'Tir à 300 mètres au Fass 90',
        description:
            'Découvre le tir à 300 mètres avec le fusil d’assaut suisse Fass 90. Accompagné par un moniteur de tir, tu apprendras les bases du tir sportif et auras l’occasion d’effectuer tes premiers tirs dans le respect des règles de sécurité.',
    },
    {
        title: 'Tir à 10 mètres avec une carabine à air comprimé',
        description:
            'Tu préfères commencer à plus courte distance\u00a0? L’initiation au tir à 10 mètres avec une carabine à air comprimé te permettra de découvrir les fondamentaux du tir sportif, notamment la concentration, la maîtrise de ta position et la précision.',
    },
];

const initiationSteps = [
    {
        icon: ShieldCheck,
        title: 'Les consignes de sécurité',
        description: 'Présentation des règles essentielles à respecter au stand de tir.',
    },
    {
        icon: Crosshair,
        title: 'Le fonctionnement de l’équipement',
        description: 'Découverte du matériel utilisé et explications sur son maniement en toute sécurité.',
    },
    {
        icon: CircleCheck,
        title: 'La pratique du tir',
        description: 'Mise en application des explications et réalisation de tes premiers tirs, sous la supervision du moniteur.',
    },
];

const groupBenefits = [
    {
        icon: Users,
        title: 'Un moment sur mesure',
        description: 'Une activité originale pour ton entreprise, ton association ou ton groupe d’amis.',
    },
    {
        icon: Utensils,
        title: 'Restauration possible',
        description: 'Partage autour d’un repas ou d’une collation, en complément de la découverte du tir sportif.',
    },
];
</script>

<template>
    <WebLayout>
        <AppHeroSection title="Initiation au tir" image="/images/hero.webp">
            <template #description>
                <Large>Découvre le tir sportif à Lavigny&nbsp;!</Large>
            </template>
        </AppHeroSection>
        <AppBreadcrumbs class="mt-96 pt-6" />

        <article class="flex w-full flex-col gap-12 py-12">
            <section class="text-pretty">
                <H2>Envie de découvrir le tir sportif&nbsp;?</H2>
                <P>
                    La Société de tir de Lavigny te propose de t’initier au tir et de vivre une nouvelle expérience dans un cadre convivial, encadré
                    et sécurisé.
                </P>
                <P>
                    Que tu sois simplement curieux de découvrir cette discipline ou que tu souhaites faire tes premiers pas avant de rejoindre notre
                    société, nous serons ravis de t’accueillir et de te faire découvrir notre passion&nbsp;!
                </P>
            </section>

            <section id="possibilites" class="scroll-mt-24 text-pretty">
                <H2>Deux possibilités pour ton initiation</H2>
                <P>Nous proposons deux types d’initiation, selon tes envies.</P>
                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card v-for="option in initiationOptions" :key="option.title" class="gap-4">
                        <CardHeader>
                            <CardTitle class="text-lg leading-tight">{{ option.title }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Muted class="leading-relaxed">{{ option.description }}</Muted>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section id="deroulement" class="scroll-mt-24 text-pretty">
                <H2>Comment se déroule une initiation&nbsp;?</H2>
                <P>
                    Chaque initiation est encadrée par un moniteur de tir qui t’accompagne tout au long de l’activité. Aucune expérience préalable
                    n’est nécessaire&nbsp;: nous prenons le temps de t’expliquer les bases et de te guider à chaque étape.
                </P>
                <P>Ton initiation comprend&nbsp;:</P>
                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card v-for="step in initiationSteps" :key="step.title" class="gap-4">
                        <CardContent class="flex flex-col gap-3">
                            <div class="bg-background flex size-10 shrink-0 items-center justify-center rounded-full border">
                                <component :is="step.icon" class="text-primary size-5" />
                            </div>
                            <p class="font-semibold">{{ step.title }}</p>
                            <Muted class="leading-relaxed">{{ step.description }}</Muted>
                        </CardContent>
                    </Card>
                </div>
                <P>
                    L’objectif est de te permettre de découvrir le tir sportif en toute confiance, à ton rythme et dans les meilleures conditions.
                </P>
            </section>

            <section id="dates" class="scroll-mt-24 text-pretty">
                <H2>Quand ont lieu les initiations&nbsp;?</H2>
                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card class="gap-4">
                        <CardHeader class="gap-3">
                            <div class="flex flex-col gap-2">
                                <CardTitle class="text-lg leading-tight">Tir à 300 mètres au Fass 90</CardTitle>
                                <Badge variant="secondary">Les jeudis</Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Muted class="leading-relaxed">
                                Les initiations ont lieu les jeudis correspondant aux jours d’ouverture de notre stand. Pour connaître les prochaines
                                dates, consulte notre
                                <TextLink :href="route('events')" class="text-primary decoration-primary!">agenda</TextLink>.
                            </Muted>
                        </CardContent>
                    </Card>
                    <Card class="gap-4">
                        <CardHeader class="gap-3">
                            <div class="flex flex-col gap-2">
                                <CardTitle class="text-lg leading-tight">Tir à 10 mètres avec une carabine à air comprimé</CardTitle>
                                <Badge variant="secondary">Sur demande</Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Muted class="leading-relaxed">
                                Les initiations ont lieu tous les jours sur demande, sauf les jeudis de tir à 300 m. Consulte notre
                                <TextLink :href="route('events')" class="text-primary decoration-primary!">agenda</TextLink> pour connaître les dates
                                concernées.
                            </Muted>
                        </CardContent>
                    </Card>
                </div>
                <Alert class="mt-6">
                    <Clock class="size-4" />
                    <AlertTitle>Nous te conseillons de nous contacter au préalable</AlertTitle>
                    <AlertDescription>
                        Cela nous permet de convenir d’une date et d’organiser ta venue dans les meilleures conditions.
                    </AlertDescription>
                </Alert>
            </section>

            <section id="prix" class="scroll-mt-24 text-pretty">
                <H2>Quel est le prix&nbsp;?</H2>
                <P>Le prix de l’initiation est de <strong>CHF 15.– par personne</strong>.</P>
                <P>
                    Et si cette première expérience te donne envie de poursuivre l’aventure avec nous, nous te réservons un petit avantage&nbsp;: si
                    tu décides de devenir membre de la Société de tir de Lavigny, le prix de ton initiation te sera remboursé sous forme de crédit de
                    tir.
                </P>
            </section>

            <section id="lancer" class="scroll-mt-24 text-pretty">
                <H2>Envie de te lancer&nbsp;?</H2>
                <P>N’hésite plus et viens découvrir le tir sportif avec la Société de tir de Lavigny&nbsp;!</P>
                <P>
                    Pour réserver ton initiation ou obtenir davantage d’informations, contacte-nous. Nous nous ferons un plaisir de répondre à tes
                    questions et de t’accompagner dans cette première découverte.
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
            </section>

            <section id="groupes" class="scroll-mt-24 text-pretty">
                <H2>Organisation d’événements et initiations de groupe</H2>
                <P>
                    Tu souhaites organiser une activité originale pour ton entreprise, ton association, ton groupe d’amis ou à l’occasion d’un
                    événement particulier&nbsp;?
                </P>
                <P>
                    Sur demande, la Société de tir de Lavigny peut également organiser des
                    <strong>événements d’initiation au tir sportif pour des groupes</strong>, avec la possibilité d’inclure une
                    <strong>prestation de restauration</strong>.
                </P>
                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card v-for="benefit in groupBenefits" :key="benefit.title" class="gap-4">
                        <CardContent class="flex flex-col gap-3">
                            <div class="bg-background flex size-10 shrink-0 items-center justify-center rounded-full border">
                                <component :is="benefit.icon" class="text-primary size-5" />
                            </div>
                            <p class="font-semibold">{{ benefit.title }}</p>
                            <Muted class="leading-relaxed">{{ benefit.description }}</Muted>
                        </CardContent>
                    </Card>
                </div>
                <P>
                    Nous pouvons ainsi te proposer un moment convivial et original, alliant découverte du tir sportif, encadrement par nos moniteurs
                    et partage autour d’un repas ou d’une collation.
                </P>
                <Alert class="mt-6">
                    <Info class="size-4" />
                    <AlertTitle>À noter</AlertTitle>
                    <AlertDescription>
                        Les initiations au tir à 300 mètres au Fass 90 sont uniquement possibles lors des jours de tir officiels de la société. Nous
                        t’invitons à consulter notre
                        <TextLink :href="route('events')" class="text-primary decoration-primary!">agenda</TextLink> pour connaître les dates
                        concernées.
                    </AlertDescription>
                </Alert>
                <P>
                    N’hésite pas à nous contacter pour discuter de ton projet et obtenir une offre adaptée à tes envies et à la taille de ton
                    groupe&nbsp;!
                </P>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <Button as-child size="lg">
                        <a
                            href="javascript:location='mailto:\u0063\u006f\u006e\u0074\u0061\u0063\u0074\u0040\u0074\u0069\u0072\u002d\u006c\u0061\u0076\u0069\u0067\u006e\u0079\u002e\u0063\u0068';void 0"
                        >
                            <Mail class="size-4" />
                            Discuter de ton projet
                        </a>
                    </Button>
                </div>
            </section>

            <p class="text-center font-semibold text-balance">Au plaisir de t’accueillir bientôt sur notre stand&nbsp;!</p>
        </article>
    </WebLayout>
</template>
