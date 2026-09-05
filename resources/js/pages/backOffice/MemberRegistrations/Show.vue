<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Muted, P } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, MemberRegistration } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { Copy, ExternalLink } from '@lucide/vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    registration: MemberRegistration;
    formUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inscriptions', href: route('bo.member-registrations.index') },
    { title: 'Détail', href: route('bo.member-registrations.show', props.registration.id) },
];

const page = usePage();

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(props.formUrl);
        toast.success('Lien copié dans le presse-papiers.');
    } catch {
        toast.error('Impossible de copier le lien.');
    }
};

const statusBadgeVariant = (status: string) => {
    switch (status) {
        case 'completed':
            return 'secondary';
        case 'error':
            return 'destructive';
        default:
            return 'default';
    }
};

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        pending: 'En attente',
        form_filled: 'Formulaire rempli',
        dav_created: 'DAV créé',
        email_sent: 'Email envoyé',
        completed: 'Terminé',
        error: 'Erreur',
    };
    return labels[status] ?? status;
};

const flash = page.props.flash as Record<string, string | undefined>;
const displayUrl = flash?.form_url ?? props.formUrl;
</script>

<template>
    <Head :title="'Inscription ' + registration.id" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center gap-3">
            <H1>Inscription #{{ registration.id }}</H1>
            <Badge :variant="statusBadgeVariant(registration.status)">
                {{ statusLabel(registration.status) }}
            </Badge>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card v-if="displayUrl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">Lien du formulaire</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <P class="bg-muted flex-1 overflow-x-auto rounded px-3 py-2 text-sm">{{ displayUrl }}</P>
                        <Button size="icon" variant="outline" @click="copyToClipboard">
                            <Copy class="size-4" />
                        </Button>
                        <Button size="icon" variant="outline" as-child>
                            <a :href="displayUrl" target="_blank" rel="noopener noreferrer">
                                <ExternalLink class="size-4" />
                            </a>
                        </Button>
                    </div>
                    <div class="text-muted-foreground mt-2 text-sm">
                        <Muted>Créé le {{ formatDate(registration.created_at, 'DD.MM.YYYY HH:mm') }}</Muted>
                        <Muted>Mis à jour le {{ formatDate(registration.updated_at, 'DD.MM.YYYY HH:mm') }}</Muted>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">Membre</CardTitle></CardHeader>
                    <CardContent>
                        <P v-if="registration.member" class="font-medium">{{ registration.member.first_name }} {{ registration.member.last_name }}</P>
                        <P v-else class="text-muted-foreground text-sm">Aucun membre associé</P>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-sm">DAV UID</CardTitle></CardHeader>
                    <CardContent>
                        <P v-if="registration.dav_uid" class="font-mono text-sm">{{ registration.dav_uid }}</P>
                        <P v-else class="text-muted-foreground text-sm">-</P>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="registration.error_message" class="border-destructive/50 bg-destructive/5">
                <CardHeader class="pb-2"><CardTitle class="text-destructive text-sm">Erreur</CardTitle></CardHeader>
                <CardContent>
                    <P class="text-sm">{{ registration.error_message }}</P>
                </CardContent>
            </Card>

            <Separator />

            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Données du formulaire</H3>
                <div class="mt-4">
                    <Pre v-if="registration.form_data" class="bg-muted max-h-96 overflow-auto rounded p-4 text-xs">{{
                        JSON.stringify(registration.form_data, null, 2)
                    }}</Pre>
                    <P v-else class="text-muted-foreground text-sm">Aucune donnée</P>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
