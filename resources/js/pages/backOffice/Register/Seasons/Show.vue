<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Season, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{ season: Season }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Saisons', href: route('bo.register.seasons.index') },
    { title: props.season.name, href: route('bo.register.seasons.show', props.season.id) },
];
</script>

<template>
    <Head :title="season.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>{{ season.name }}</H1>
            <Tooltip v-if="hasPermissionTo(user, 'manage seasons')">
                <TooltipTrigger>
                    <Link :href="route('bo.register.seasons.edit', season.id)">
                        <Button size="icon" variant="outline"><Pencil /></Button>
                    </Link>
                </TooltipTrigger>
                <TooltipContent><p>Modifier</p></TooltipContent>
            </Tooltip>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Détails</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Date de début</dt>
                            <dd class="font-medium">{{ formatDate(season.start_date, 'dd.MM.yyyy') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Date de fin</dt>
                            <dd class="font-medium">{{ formatDate(season.end_date, 'dd.MM.yyyy') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Solde initial</dt>
                            <dd class="font-medium">{{ season.initial_balance.toFixed(2) }} CHF</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Solde final</dt>
                            <dd class="font-medium">{{ season.end_balance?.toFixed(2) ?? '—' }} CHF</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Separator />

            <div class="flex items-center justify-between">
                <H3>Journées d'opération</H3>
                <Badge variant="secondary">{{ season.operatingDays?.length ?? 0 }}</Badge>
            </div>

            <div v-if="season.operatingDays?.length" class="flex flex-col gap-2">
                <Link
                    v-for="day in season.operatingDays"
                    :key="day.id"
                    :href="route('bo.register.operating-days.show', day.id)"
                    class="hover:bg-accent flex items-center justify-between rounded-lg border p-4"
                >
                    <div>
                        <p class="font-medium">{{ formatDate(day.date, 'dd.MM.yyyy') }}</p>
                        <p class="text-muted-foreground text-sm">
                            Espèces: {{ day.total_cash.toFixed(2) }} CHF | Twint: {{ day.total_twint.toFixed(2) }} CHF
                        </p>
                    </div>
                    <Badge :variant="day.closed_at ? 'secondary' : 'default'">
                        {{ day.closed_at ? 'Fermé' : 'Ouvert' }}
                    </Badge>
                </Link>
            </div>
            <p v-else class="text-muted-foreground text-sm">Aucune journée d'opération.</p>
        </div>
    </AppLayout>
</template>
