<script setup lang="ts">
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import { ActivityLog, type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarRange,
    Layers,
    Pencil,
    Plus,
    User,
    Calendar,
    CalendarDays,
    FileText,
    MapPin,
    ShoppingCart,
    Tag,
    Ticket,
    Trash2,
    Users,
} from '@lucide/vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tableau de bord',
        href: route('dashboard'),
    },
];

defineProps<{
    activities: ActivityLog[];
}>();

const actionConfig = (action: string) => {
    switch (action) {
        case 'created':
            return {
                label: 'Créé',
                icon: Plus,
                class: 'border-green-200 bg-green-500/10 text-green-700 dark:border-green-800 dark:text-green-400',
                dot: 'bg-muted-foreground',
            };
        case 'updated':
            return {
                label: 'Modifié',
                icon: Pencil,
                class: 'border-blue-200 bg-blue-500/10 text-blue-700 dark:border-blue-800 dark:text-blue-400',
                dot: 'bg-muted-foreground',
            };
        case 'deleted':
            return {
                label: 'Supprimé',
                icon: Trash2,
                class: 'border-red-200 bg-red-500/10 text-red-700 dark:border-red-800 dark:text-red-400',
                dot: 'bg-muted-foreground',
            };
        default:
            return { label: action, icon: null, class: '', dot: 'bg-muted-foreground' };
    }
};

const modelIcon = (model: string) => {
    switch (cleanModelName(model)) {
        case 'post':
            return FileText;
        case 'event':
            return Calendar;
        case 'eventchain':
            return CalendarRange;
        case 'location':
            return MapPin;
        case 'eventcontent':
            return Layers;
        case 'user':
            return User;
        case 'ticket':
            return Ticket;
        case 'season':
            return CalendarDays;
        case 'product':
            return ShoppingCart;
        case 'category':
            return Tag;
        case 'member':
            return Users;
        case 'voucher':
            return Tag;
        case 'operatingday':
            return CalendarDays;
        default:
            return FileText;
    }
};

const cleanModelName = (model: string) => {
    return model.split('\\').pop()?.toLowerCase() ?? model.toLowerCase();
};

const translateModel = (model: string) => {
    switch (cleanModelName(model)) {
        case 'post':
            return 'article';
        case 'event':
            return 'événement';
        case 'eventchain':
            return "série d'événements";
        case 'location':
            return 'lieu';
        case 'content':
            return 'contenu';
        case 'eventcontent':
            return "contenu d'événement";
        case 'user':
            return 'utilisateur';
        case 'activitylog':
            return "journal d'activité";
        case 'ticket':
            return 'ticket';
        case 'ticketitem':
            return 'élément de ticket';
        case 'season':
            return 'saison';
        case 'product':
            return 'produit';
        case 'category':
            return 'catégorie';
        case 'member':
            return 'membre';
        case 'voucher':
            return 'bon';
        case 'operatingday':
            return "jour d'ouverture";
        default:
            return cleanModelName(model);
    }
};
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <!-- Display some register stats -->
                    <PlaceholderPattern />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <!-- Display some events stats -->
                    <PlaceholderPattern />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <!-- Display some website visit stats if infomanial has an API -->
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <H3 class="mb-4">Activité récente</H3>
                <ScrollArea v-if="activities.length" class="h-[500px] pr-4">
                    <ol class="relative">
                        <li v-for="(activity, index) in activities" :key="activity.id" class="relative flex gap-4 pb-8 last:pb-0">
                            <!-- Timeline line -->
                            <div v-if="index < activities.length - 1" class="bg-border absolute top-6 left-[11px] h-full w-px" />

                            <!-- Dot -->
                            <div
                                class="border-background relative z-10 mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 shadow-sm"
                                :class="actionConfig(activity.action).dot"
                            />

                            <!-- Content -->
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <div class="flex flex-col gap-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge class="gap-1" :class="actionConfig(activity.action).class">
                                            <component :is="actionConfig(activity.action).icon" class="h-3 w-3" />
                                            {{ actionConfig(activity.action).label }}
                                        </Badge>
                                        <span class="flex items-center gap-1.5 text-sm font-medium">
                                            <component :is="modelIcon(activity.model)" class="text-muted-foreground h-3.5 w-3.5" />
                                            {{ translateModel(activity.model) }}
                                        </span>
                                    </div>
                                    <p class="text-muted-foreground text-xs">
                                        {{ formatDate(activity.created_at, 'ddd DD MMMM YYYY à HH:mm') }}
                                    </p>
                                </div>
                                <Button
                                    v-if="route().has(`bo.${cleanModelName(activity.model)}s.show`)"
                                    :variant="activity.action === 'deleted' ? 'outline' : 'outline'"
                                    :disabled="activity.action === 'deleted'"
                                    size="icon"
                                    class="mt-0.5 shrink-0"
                                    as-child
                                >
                                    <Link :href="route(`bo.${cleanModelName(activity.model)}s.show`, activity.model_id)">
                                        <ArrowRight class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        </li>
                    </ol>
                </ScrollArea>
                <div v-else class="flex h-full w-full flex-col items-center justify-center gap-4 py-16">
                    <Muted>Aucune activité récente</Muted>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
