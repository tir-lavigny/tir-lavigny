<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, BellRing, Clock, FileText, Mail, Printer, Receipt, Wallet } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    summary: {
        year: number;
        issued_count: number;
        issued_total_cents: number;
        paid_cents: number;
        credited_cents: number;
        outstanding_cents: number;
        overdue_count: number;
        overdue_cents: number;
        awaiting_payment_count: number;
        awaiting_email_count: number;
        awaiting_postal_count: number;
        reminders_count: number;
        status_counts: Array<{ value: string; label: string; count: number }>;
    };
    years: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord facturation', href: route('bo.invoicing.dashboard') }];

const selectedYear = computed({
    get: () => String(props.summary.year),
    set: (value: string) => {
        router.get(route('bo.invoicing.dashboard'), { year: value }, { preserveState: true, preserveScroll: true });
    },
});
</script>

<template>
    <Head title="Tableau de bord facturation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Tableau de bord facturation</H1>
                <p class="text-muted-foreground mt-2 text-sm">Indicateurs financiers et opérations en attente.</p>
            </div>
            <div class="grid gap-2 md:w-40">
                <Label for="year">Année</Label>
                <Select v-model="selectedYear">
                    <SelectTrigger id="year" class="w-full">
                        <SelectValue placeholder="Année" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="year in years" :key="year" :value="String(year)">{{ year }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="mx-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Link class="block h-full" :href="route('bo.invoicing.invoices.index', { year: summary.year })">
                <Card class="h-full transition hover:border-blue-400">
                    <CardHeader>
                        <CardDescription class="flex items-center gap-2"><FileText class="h-4 w-4" /> Factures émises</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.issued_count }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">
                        {{ formatMoney(summary.issued_total_cents / 100) }} CHF facturés
                    </CardContent>
                </Card>
            </Link>

            <Link class="block h-full" :href="route('bo.invoicing.payments.index', { year: summary.year })">
                <Card class="h-full transition hover:border-blue-400">
                    <CardHeader>
                        <CardDescription class="flex items-center gap-2"><Wallet class="h-4 w-4" /> Encaissé</CardDescription>
                        <CardTitle class="text-2xl">{{ formatMoney(summary.paid_cents / 100) }} CHF</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm"
                        >Crédits appliqués : {{ formatMoney(summary.credited_cents / 100) }} CHF</CardContent
                    >
                </Card>
            </Link>

            <Link class="block h-full" :href="route('bo.invoicing.invoices.index', { year: summary.year })">
                <Card class="h-full transition hover:border-blue-400">
                    <CardHeader>
                        <CardDescription class="flex items-center gap-2"><Clock class="h-4 w-4" /> Solde ouvert</CardDescription>
                        <CardTitle class="text-2xl">{{ formatMoney(summary.outstanding_cents / 100) }} CHF</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm"
                        >{{ summary.awaiting_payment_count }} facture(s) en attente de paiement</CardContent
                    >
                </Card>
            </Link>

            <Link class="block h-full" :href="route('bo.invoicing.reminders.overdue.index', { year: summary.year })">
                <Card class="h-full transition hover:border-red-400">
                    <CardHeader>
                        <CardDescription class="flex items-center gap-2"><AlertTriangle class="h-4 w-4" /> En retard</CardDescription>
                        <CardTitle class="text-2xl text-red-600">{{ formatMoney(summary.overdue_cents / 100) }} CHF</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">{{ summary.overdue_count }} facture(s) en retard</CardContent>
                </Card>
            </Link>
        </div>

        <div class="mx-4 grid gap-4 md:grid-cols-3">
            <Link class="block h-full" :href="route('bo.invoicing.deliveries.email.index', { year: summary.year })">
                <Card class="h-full transition hover:border-blue-400">
                    <CardHeader>
                        <CardDescription class="flex items-center gap-2"><Mail class="h-4 w-4" /> Emails à envoyer</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.awaiting_email_count }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">Factures en attente de distribution email.</CardContent>
                </Card>
            </Link>

            <Link class="block h-full" :href="route('bo.invoicing.deliveries.postal.index', { year: summary.year })">
                <Card class="h-full transition hover:border-blue-400">
                    <CardHeader>
                        <CardDescription class="flex items-center gap-2"><Printer class="h-4 w-4" /> Impressions à faire</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.awaiting_postal_count }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">Factures en attente d’impression postale.</CardContent>
                </Card>
            </Link>

            <Link class="block h-full" :href="route('bo.invoicing.reminders.overdue.index', { year: summary.year })">
                <Card class="h-full transition hover:border-blue-400">
                    <CardHeader>
                        <CardDescription class="flex items-center gap-2"><BellRing class="h-4 w-4" /> Rappels traités</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.reminders_count }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">Rappels envoyés ou imprimés pendant l’année.</CardContent>
                </Card>
            </Link>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <H3>Factures par statut</H3>
            <div class="mt-4 flex flex-wrap gap-2">
                <Badge v-for="status in summary.status_counts" :key="status.value" variant="secondary">
                    <Receipt class="mr-1 h-3 w-3" /> {{ status.label }} : {{ status.count }}
                </Badge>
            </div>
        </div>
    </AppLayout>
</template>
