<script setup lang="ts">
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Stepper, StepperDescription, StepperItem, StepperSeparator, StepperTitle, StepperTrigger } from '@/components/ui/stepper';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import { ActivityLog, type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tableau de bord',
        href: route('dashboard'),
    },
];

defineProps<{
    activities: ActivityLog[];
}>();

const translate = (key: string) => {
    switch (key) {
        case 'created':
            return 'Créé';
        case 'updated':
            return 'Modifié';
        case 'deleted':
            return 'Supprimé';
        default:
            return key;
    }
};

const cleanModelName = (model: string) => {
    return model.split('\\').pop()?.toLowerCase() ?? model.toLowerCase();
};
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <PlaceholderPattern />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <PlaceholderPattern />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <H3>Activité récente</H3>
                <Stepper v-if="activities.length" orientation="vertical" class="mx-auto flex w-full max-w-md flex-col items-center gap-10">
                    <StepperItem v-for="(activity, index) in activities" :key="activity.id" :step="index">
                        <StepperTrigger>
                            <StepperTitle>
                                {{ translate(activity.action) }} un {{ cleanModelName(activity.model) }}

                                <Button
                                    :variant="activity.action === 'deleted' ? 'outline' : 'secondary'"
                                    :disabled="activity.action === 'deleted'"
                                    size="icon"
                                    v-if="route().has(`bo.${cleanModelName(activity.model)}s.show`)"
                                    class="ml-2"
                                >
                                    <Link
                                        class="flex items-center gap-2"
                                        :href="route(`bo.${cleanModelName(activity.model)}s.show`, activity.model_id)"
                                    >
                                        <ArrowRight class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </StepperTitle>
                            <StepperDescription> {{ formatDate(activity.created_at, 'ddd DD MMMM YYYY à HH:mm') }} </StepperDescription>
                        </StepperTrigger>
                        <StepperSeparator />
                    </StepperItem>
                </Stepper>
                <div v-else class="flex h-full w-full flex-col items-center justify-center gap-4">
                    <Muted> Aucune activité récente </Muted>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
