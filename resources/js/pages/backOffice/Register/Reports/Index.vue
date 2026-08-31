<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, Season } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, CalendarRange } from '@lucide/vue';

defineProps<{
    seasons: (Season & { operating_days_count: number })[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rapports', href: route('bo.register.reports.index') }];
</script>

<template>
    <Head title="Rapports" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Rapports</H1>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Link :href="route('bo.register.reports.period')" class="block">
                <Card class="hover:bg-accent/50 transition-colors">
                    <CardHeader class="flex flex-row items-center gap-3 pb-2">
                        <CalendarRange class="text-muted-foreground size-5" />
                        <CardTitle class="text-base">Rapport par période</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground text-sm">Consulter les rapports pour une période personnalisée.</p>
                    </CardContent>
                </Card>
            </Link>

            <H3>Rapport par saison</H3>

            <template v-if="seasons.length">
                <Link v-for="season in seasons" :key="season.id" :href="route('bo.register.reports.season', season.id)" class="block">
                    <Card class="hover:bg-accent/50 transition-colors">
                        <CardHeader class="flex flex-row items-center gap-3 pb-2">
                            <CalendarDays class="text-muted-foreground size-5" />
                            <CardTitle class="text-base">{{ season.name }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-muted-foreground text-sm">
                                {{ formatDate(season.start_date, 'DD.MM.YYYY') }} — {{ formatDate(season.end_date, 'DD.MM.YYYY') }} ·
                                {{ season.operating_days_count }} journée(s)
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </template>
            <Muted v-else class="flex items-center justify-center">Aucune saison trouvée.</Muted>
        </div>
    </AppLayout>
</template>
