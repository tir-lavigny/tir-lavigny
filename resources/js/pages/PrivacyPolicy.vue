<script setup lang="ts">
import AppBreadcrumbs from '@/components/AppBreadcrumbs.vue';
import { H1 } from '@/components/typography/headings';
import WebLayout from '@/layouts/WebLayout.vue';
</script>

<template>
    <WebLayout>
        <AppBreadcrumbs class="mt-16" />
        <section class="flex flex-col gap-8 pb-12">
            <H1>Déclaration de protection des données</H1>
            <div class="prose dark:prose-invert xl:prose-xl max-w-full">
                <h2>1. Responsable du traitement</h2>
                <p>Le responsable du traitement des données personnelles est :</p>
                <p>Société de tir de Lavigny</p>
                <p>
                    Adresse de correspondance :<br />
                    Thévoz Emilie<br />
                    Route de Pampigny 4<br />
                    1143 Apples
                </p>
                <p>
                    <a
                        href="javascript:location='mailto:\u0063\u006f\u006e\u0074\u0061\u0063\u0074\u0040\u0074\u0069\u0072\u002d\u006c\u0061\u0076\u0069\u0067\u006e\u0079\u002e\u0063\u0068';void 0"
                        class="flex items-center"
                    >
                        E-mail
                    </a>
                </p>

                <h2>2. Données personnelles collectées</h2>
                <p>Dans le cadre d'une demande d'adhésion, la société collecte les données personnelles suivantes :</p>
                <ul>
                    <li>Nom et prénom ;</li>
                    <li>Adresse e-mail ;</li>
                    <li>Numéro de téléphone ;</li>
                    <li>Adresse postale (rue, numéro, NPA, localité, canton et pays) ;</li>
                    <li>Nationalité suisse (indication permettant de déterminer si la personne possède la nationalité suisse) ;</li>
                    <li>Numéro AVS.</li>
                </ul>

                <h2>3. Finalités du traitement</h2>
                <p>Les données personnelles sont collectées et traitées dans les buts suivants :</p>
                <ul>
                    <li>Gestion des demandes d'adhésion et enregistrement des nouveaux membres ;</li>
                    <li>Création et tenue du registre des membres ;</li>
                    <li>Gestion administrative de la société ;</li>
                    <li>Enregistrement des membres dans le système SAT-Admin de la Confédération suisse ;</li>
                    <li>Communication avec les membres concernant les activités, événements et informations de la société ;</li>
                    <li>Gestion des cotisations et des éventuelles obligations comptables ;</li>
                    <li>Respect des obligations légales et administratives applicables.</li>
                </ul>
                <p>
                    Le numéro AVS est collecté afin de permettre l'identification correcte des membres et leur enregistrement dans le système
                    SAT-Admin de la Confédération suisse.
                </p>

                <h2>4. Accès et communication des données</h2>
                <p>
                    Les données personnelles sont accessibles uniquement aux membres du comité et aux personnes autorisées qui en ont besoin dans le
                    cadre de leurs fonctions.
                </p>
                <p>
                    Dans le cadre des démarches administratives, les données nécessaires, notamment le numéro AVS, peuvent être transmises au système
                    SAT-Admin de la Confédération suisse.
                </p>
                <p>La société ne vend pas les données personnelles à des tiers.</p>

                <h2>5. Hébergement, conservation et sécurité des données</h2>
                <p>
                    Les systèmes informatiques de la société sont gérés en interne. Les données personnelles ainsi que les sauvegardes sont hébergées
                    et conservées en Suisse, auprès d'Infomaniak.
                </p>
                <p>
                    La société met en œuvre des mesures techniques et organisationnelles appropriées afin de protéger les données personnelles contre
                    tout accès non autorisé, perte, modification ou divulgation.
                </p>
                <p>Les numéros AVS bénéficient de mesures de protection renforcées :</p>
                <ul>
                    <li>Ils sont chiffrés à l'aide d'un mécanisme de chiffrement AES-256 ;</li>
                    <li>Leur accès en clair est limité à un nombre restreint de membres autorisés du comité ;</li>
                    <li>Les sauvegardes de la base de données conservent les numéros AVS sous forme chiffrée ;</li>
                    <li>Les clés de déchiffrement sont conservées séparément des sauvegardes.</li>
                </ul>
                <p>
                    Les données personnelles sont conservées pendant la durée nécessaire à la gestion de la relation avec les membres et au
                    fonctionnement de la société.
                </p>
                <p>
                    Après la fin de l'adhésion, les données sont supprimées ou anonymisées lorsqu'elles ne sont plus nécessaires, sous réserve des
                    obligations légales de conservation, notamment en matière comptable.
                </p>

                <h2>6. Droits des personnes concernées</h2>
                <p>Conformément à la loi fédérale sur la protection des données (LPD), toute personne concernée peut notamment :</p>
                <ul>
                    <li>Demander des renseignements sur les données personnelles la concernant ;</li>
                    <li>Demander la rectification de données inexactes ;</li>
                    <li>Demander la suppression de ses données, dans la mesure où aucune obligation légale ne s'y oppose ;</li>
                    <li>S'opposer à certains traitements lorsque les conditions légales le permettent ;</li>
                    <li>Faire valoir les autres droits prévus par la législation applicable.</li>
                </ul>
                <p>
                    Toute demande relative à la protection des données peut être adressée à la société à l'adresse e-mail indiquée dans la présente
                    déclaration.
                </p>

                <h2>7. Modification de la déclaration</h2>
                <p>
                    La société se réserve le droit de modifier la présente déclaration lorsque cela est nécessaire, notamment en cas d'évolution de
                    ses activités, de ses outils informatiques ou de la législation applicable.
                </p>
            </div>
        </section>
    </WebLayout>
</template>
