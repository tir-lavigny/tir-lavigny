<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H1, H3, H4 } from '@/components/typography/headings';
import { Muted, P, Small } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import { ChainEvent, EventChain, PaginatedChainEvents, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, Eye, Link2Off, PenBox, RefreshCcw, Repeat, Trash2 } from 'lucide-vue-next';
import { marked } from 'marked';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    chain: EventChain;
    paginatedEvents: PaginatedChainEvents;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Séries d'événements",
        href: route('bo.event-chains.index'),
    },
    {
        title: props.chain.title,
        href: route('bo.event-chains.show', props.chain.id),
    },
];

// ─── Labels ───────────────────────────────────────────────────────────────────

const recurrenceLabel: Record<string, string> = {
    daily: 'Quotidien',
    weekly: 'Hebdomadaire',
    biweekly: 'Bihebdomadaire',
    monthly: 'Mensuel',
    custom: 'Personnalisé',
};

const fieldLabels: Record<string, string> = {
    title: 'Titre',
    location_id: 'Lieu',
    content_id: 'Template de contenu',
    start_time: 'Heure de début',
    end_time: 'Heure de fin',
    custom_body: 'Contenu personnalisé',
};

const chainFieldValue = (field: string): string => {
    const chain = props.chain as unknown as Record<string, unknown>;
    const val = chain[field];
    if (val === null || val === undefined) return '-';
    if (field === 'location_id') return props.chain.location?.name ?? String(val);
    return String(val);
};

const eventFieldValue = (event: ChainEvent, field: string): string => {
    switch (field) {
        case 'title':
            return event.title ?? '-';
        case 'location_id':
            return event.location?.name ?? (event.location_id ? String(event.location_id) : '-');
        case 'content_id':
            return event.content_id ? String(event.content_id) : '-';
        case 'start_time':
            return event.start_datetime ? new Date(event.start_datetime).toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' }) : '-';
        case 'end_time':
            return event.end_datetime ? new Date(event.end_datetime).toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' }) : '-';
        case 'custom_body':
            return event.custom_content?.body ?? '-';
        default:
            return '-';
    }
};

// ─── Conflict Dialog ──────────────────────────────────────────────────────────

const conflictDialogOpen = ref(false);
const selectedEvent = ref<ChainEvent | null>(null);
const resolvingField = ref<string | null>(null);

const openConflictDialog = (event: ChainEvent) => {
    selectedEvent.value = event;
    conflictDialogOpen.value = true;
};

const resolveField = (field: string, resolution: 'accept_chain' | 'keep_override') => {
    if (!selectedEvent.value) return;
    resolvingField.value = field;

    const form = useForm({ field, resolution });
    form.post(
        route('bo.event-chains.resolve-conflict', {
            eventChain: props.chain.id,
            event: selectedEvent.value.id,
        }),
        {
            preserveState: false,
            onSuccess: () => {
                toast.success(`Conflit résolu pour « ${fieldLabels[field] ?? field} ».`);
                resolvingField.value = null;
                // Close dialog if no more conflicts
                const remaining = (selectedEvent.value?.pending_conflict_fields ?? []).filter((f) => f !== field);
                if (remaining.length === 0) conflictDialogOpen.value = false;
            },
            onError: () => {
                toast.error('Erreur lors de la résolution du conflit.');
                resolvingField.value = null;
            },
        },
    );
};

// ─── Detach ───────────────────────────────────────────────────────────────────

const detachEvent = (eventId: number) => {
    const form = useForm({});
    form.post(route('bo.events.detach', { event: eventId }), {
        onSuccess: () => toast.success('Événement détaché de la série.'),
        onError: () => toast.error('Erreur lors du détachement.'),
    });
};

// ─── Reset to chain ──────────────────────────────────────────────────────────

const resetToChain = (eventId: number) => {
    const form = useForm({});
    form.post(route('bo.event-chains.reset-to-chain', { eventChain: props.chain.id, event: eventId }), {
        onSuccess: () => toast.success('Événement réinitialisé aux valeurs de la série.'),
        onError: () => toast.error('Erreur lors de la réinitialisation.'),
    });
};

// ─── Delete chain ────────────────────────────────────────────────────────────

const deleteChain = () => {
    const form = useForm({});
    form.delete(route('bo.event-chains.destroy', props.chain.id), {
        onSuccess: () => toast.success('Série et événements supprimés.'),
        onError: () => toast.error('Erreur lors de la suppression.'),
    });
};

// ─── Pagination ───────────────────────────────────────────────────────────────

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.event-chains.show', props.chain.id), { page: p }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head :title="chain.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <div class="m-4 flex flex-row items-center justify-between">
            <div class="flex items-center gap-3">
                <Repeat class="text-muted-foreground size-6" />
                <H1>{{ chain.title }}</H1>
            </div>
            <div class="flex flex-row items-center gap-2">
                <Tooltip v-if="hasPermissionTo(user, 'edit event chain')">
                    <TooltipTrigger>
                        <Link :href="route('bo.event-chains.edit', chain.id)">
                            <Button size="icon" variant="secondary">
                                <PenBox />
                            </Button>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent><p>Modifier la série</p></TooltipContent>
                </Tooltip>
                <Tooltip v-if="hasPermissionTo(user, 'delete event chain')">
                    <TooltipTrigger>
                        <AppAlertDialog
                            title="Supprimer la série ?"
                            description="Cette action est irréversible. La série et tous ses événements seront définitivement supprimés."
                            cancelText="Annuler"
                        >
                            <template #trigger>
                                <Button size="icon" variant="destructive">
                                    <Trash2 />
                                </Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="deleteChain">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </TooltipTrigger>
                    <TooltipContent><p>Supprimer la série</p></TooltipContent>
                </Tooltip>
            </div>
        </div>

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Chain summary cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- General info -->
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative rounded-xl border p-4">
                    <H4>Informations</H4>
                    <div class="mt-4 flex flex-col gap-2">
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Récurrence</Small>
                            <Badge variant="secondary" class="col-span-2 w-fit">
                                {{ recurrenceLabel[chain.recurrence_type] ?? chain.recurrence_type }}
                            </Badge>
                        </div>
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Début</Small>
                            <P class="col-span-2 !mt-0">{{ formatDate(chain.start_date, 'DD MMM YYYY') }}</P>
                        </div>
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Fin</Small>
                            <P class="col-span-2 !mt-0">{{ formatDate(chain.end_date, 'DD MMM YYYY') }}</P>
                        </div>
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Horaires</Small>
                            <P class="col-span-2 !mt-0">
                                {{ chain.start_time.slice(0, 5) }}{{ chain.end_time ? ' - ' + chain.end_time.slice(0, 5) : '' }}
                            </P>
                        </div>
                    </div>
                </div>

                <!-- Recurrence details -->
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative rounded-xl border p-4">
                    <H4>Détails de récurrence</H4>
                    <div class="mt-4 flex flex-col gap-2">
                        <div v-if="chain.day_of_week !== null" class="grid grid-cols-3 items-center gap-2">
                            <Small>Jour</Small>
                            <P class="col-span-2 !mt-0">
                                {{ ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][chain.day_of_week] }}
                            </P>
                        </div>
                        <div v-if="chain.day_of_month !== null" class="grid grid-cols-3 items-center gap-2">
                            <Small>Jour du mois</Small>
                            <P class="col-span-2 !mt-0">{{ chain.day_of_month }}</P>
                        </div>
                        <div v-if="chain.interval !== null" class="grid grid-cols-3 items-center gap-2">
                            <Small>Intervalle</Small>
                            <P class="col-span-2 !mt-0">{{ chain.interval }} {{ chain.interval_unit }}</P>
                        </div>
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Événements</Small>
                            <P class="col-span-2 !mt-0">{{ paginatedEvents.total }}</P>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative rounded-xl border p-4">
                    <H4>Lieu</H4>
                    <div class="mt-4">
                        <div v-if="chain.location">
                            <P>{{ chain.location.name }}</P>
                            <p class="text-muted-foreground text-sm">{{ chain.location.address }}</p>
                        </div>
                        <P v-else class="text-muted-foreground">Non défini</P>
                    </div>
                </div>
            </div>

            <!-- Events list -->
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex items-center justify-between">
                    <H3>Événements</H3>
                    <Badge variant="outline">{{ paginatedEvents.total }} au total</Badge>
                </div>
                <Separator class="mb-4" />

                <div v-if="paginatedEvents.data && paginatedEvents.data.length > 0" class="flex flex-col gap-3">
                    <div
                        v-for="event in paginatedEvents.data"
                        :key="event.id"
                        class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                        :class="{
                            'border-warning': event.pending_conflict_fields && event.pending_conflict_fields.length > 0,
                        }"
                    >
                        <!-- Event info -->
                        <div class="flex items-start gap-3">
                            <CalendarDays
                                class="mt-0.5 size-5 shrink-0"
                                :class="
                                    event.pending_conflict_fields && event.pending_conflict_fields.length > 0
                                        ? 'text-warning'
                                        : 'text-muted-foreground'
                                "
                            />
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ event.title }}</span>
                                    <!-- Pending conflicts needing a decision -->
                                    <Badge
                                        v-if="event.pending_conflict_fields && event.pending_conflict_fields.length > 0"
                                        variant="outline"
                                        class="border-warning text-warning-foreground"
                                    >
                                        <AlertTriangle class="mr-1 size-3" />
                                        {{ event.pending_conflict_fields.length }} conflit{{ event.pending_conflict_fields.length > 1 ? 's' : '' }}
                                    </Badge>
                                    <!-- No pending decisions but event intentionally diverges from chain -->
                                    <Badge v-else-if="event.has_chain_conflict" variant="secondary"> Diverge de la série </Badge>
                                    <Badge v-else-if="event.overridden_fields && event.overridden_fields.length > 0" variant="secondary">
                                        Modifié
                                    </Badge>
                                </div>
                                <p class="text-muted-foreground text-sm">
                                    {{ formatDate(event.start_datetime, 'dddd DD MMMM YYYY à HH:mm') }}
                                    <span v-if="event.end_datetime"> → {{ formatDate(event.end_datetime, 'HH:mm') }} </span>
                                </p>
                                <p v-if="event.location" class="text-muted-foreground text-sm">{{ event.location.name }}</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <!-- Conflict resolution: only when there are pending decisions -->
                            <Tooltip
                                v-if="
                                    event.pending_conflict_fields &&
                                    event.pending_conflict_fields.length > 0 &&
                                    hasPermissionTo(user, 'edit event chain')
                                "
                            >
                                <TooltipTrigger>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="border-warning text-warning-foreground hover:bg-warning-subtle"
                                        @click="openConflictDialog(event)"
                                    >
                                        <AlertTriangle class="mr-1 size-4" />
                                        Résoudre
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent><p>Résoudre les conflits de cet événement</p></TooltipContent>
                            </Tooltip>

                            <!-- Reset to chain: only for events that diverge but have no pending conflicts -->
                            <Tooltip
                                v-if="
                                    ((event.has_chain_conflict && (!event.pending_conflict_fields || event.pending_conflict_fields.length === 0)) ||
                                        (event.overridden_fields && event.overridden_fields.length > 0)) &&
                                    hasPermissionTo(user, 'edit event chain')
                                "
                            >
                                <TooltipTrigger>
                                    <AppAlertDialog
                                        title="Réinitialiser aux valeurs de la série ?"
                                        description="Toutes vos modifications sur cet événement seront écrasées et remplacées par les valeurs actuelles de la série. Cette action est irréversible."
                                        cancelText="Annuler"
                                    >
                                        <template #trigger>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                class="text-warning hover:bg-warning-subtle hover:text-warning-foreground"
                                            >
                                                <RefreshCcw />
                                            </Button>
                                        </template>
                                        <template #action>
                                            <Button variant="destructive" @click="resetToChain(event.id)">Réinitialiser</Button>
                                        </template>
                                    </AppAlertDialog>
                                </TooltipTrigger>
                                <TooltipContent><p>Réinitialiser aux valeurs de la série</p></TooltipContent>
                            </Tooltip>

                            <!-- View event -->
                            <Tooltip>
                                <TooltipTrigger>
                                    <Link :href="route('bo.events.show', event.id)">
                                        <Button size="icon" variant="outline">
                                            <Eye />
                                        </Button>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent><p>Voir l'événement</p></TooltipContent>
                            </Tooltip>

                            <!-- Edit event -->
                            <Tooltip v-if="hasPermissionTo(user, 'edit event')">
                                <TooltipTrigger>
                                    <Link :href="route('bo.events.edit', event.id)">
                                        <Button size="icon" variant="outline">
                                            <PenBox />
                                        </Button>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent><p>Modifier l'événement</p></TooltipContent>
                            </Tooltip>

                            <!-- Detach -->
                            <Tooltip v-if="hasPermissionTo(user, 'edit event chain')">
                                <TooltipTrigger>
                                    <AppAlertDialog
                                        title="Détacher l'événement ?"
                                        description="L'événement sera conservé mais ne sera plus lié à cette série. Les modifications de la série ne s'y appliqueront plus."
                                        cancelText="Annuler"
                                    >
                                        <template #trigger>
                                            <Button size="icon" variant="ghost">
                                                <Link2Off />
                                            </Button>
                                        </template>
                                        <template #action>
                                            <Button variant="secondary" @click="detachEvent(event.id)">Détacher</Button>
                                        </template>
                                    </AppAlertDialog>
                                </TooltipTrigger>
                                <TooltipContent><p>Dissocier cet événement de la série</p></TooltipContent>
                            </Tooltip>
                        </div>
                    </div>
                </div>

                <Muted v-if="!paginatedEvents.data || paginatedEvents.data.length === 0" class="my-4 flex items-center justify-center">
                    Aucun événement dans cette série.
                </Muted>

                <AppPagination
                    :items-per-page="paginatedEvents.per_page"
                    :total-items="paginatedEvents.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="paginatedEvents.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>

    <!-- Conflict resolution dialog -->
    <Dialog v-model:open="conflictDialogOpen">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <AlertTriangle class="text-warning size-5" />
                    Résoudre les conflits
                </DialogTitle>
                <DialogDescription>
                    Certains champs de cet événement ont été modifiés manuellement et diffèrent des valeurs de la série. Choisissez pour chaque champ
                    l'action à effectuer.
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 py-2">
                <div v-for="field in selectedEvent?.pending_conflict_fields" :key="field" class="flex flex-col gap-2 rounded-lg border p-3">
                    <p class="font-medium">{{ fieldLabels[field] ?? field }}</p>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="bg-accent flex flex-col gap-1 rounded-md p-3">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Valeur de la série</p>
                            <div
                                v-if="field === 'custom_body'"
                                class="prose prose-sm dark:prose-invert max-h-40 overflow-y-auto"
                                v-html="marked(chainFieldValue(field))"
                            />
                            <p v-else class="leading-snug">{{ chainFieldValue(field) }}</p>
                        </div>
                        <div class="bg-accent flex flex-col gap-1 rounded-md p-3">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Votre modification</p>
                            <div
                                v-if="field === 'custom_body'"
                                class="prose prose-sm dark:prose-invert max-h-40 overflow-y-auto"
                                v-html="marked(eventFieldValue(selectedEvent!, field))"
                            />
                            <p v-else class="leading-snug">{{ eventFieldValue(selectedEvent!, field) }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            variant="default"
                            :disabled="resolvingField === field"
                            @click="resolveField(field, 'accept_chain')"
                            class="flex-1"
                        >
                            Accepter la série
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="resolvingField === field"
                            @click="resolveField(field, 'keep_override')"
                            class="flex-1"
                        >
                            Conserver ma valeur
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
