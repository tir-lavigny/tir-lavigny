<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import { ChainEvent, User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, Eye, Link2Off, PenBox, RefreshCcw } from 'lucide-vue-next';

defineProps<{
    event: ChainEvent;
    user: User;
}>();

const emit = defineEmits<{
    'open-conflict-dialog': [event: ChainEvent];
    'reset-to-chain': [eventId: number];
    'detach-event': [eventId: number];
}>();
</script>

<template>
    <div
        class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
        :class="{
            'border-warning': event.pending_conflict_fields && event.pending_conflict_fields.length > 0,
        }"
    >
        <!-- Event info -->
        <div class="flex items-start gap-3">
            <CalendarDays
                class="mt-0.5 size-5 shrink-0"
                :class="event.pending_conflict_fields && event.pending_conflict_fields.length > 0 ? 'text-warning' : 'text-muted-foreground'"
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
                    <Badge v-else-if="event.overridden_fields && event.overridden_fields.length > 0" variant="secondary"> Modifié </Badge>
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
            <Tooltip v-if="event.pending_conflict_fields && event.pending_conflict_fields.length > 0 && hasPermissionTo(user, 'edit event chain')">
                <TooltipTrigger>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-warning text-warning-foreground hover:bg-warning-subtle"
                        @click="emit('open-conflict-dialog', event)"
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
                            <Button size="icon" variant="ghost" class="text-warning hover:bg-warning-subtle hover:text-warning-foreground">
                                <RefreshCcw />
                            </Button>
                        </template>
                        <template #action>
                            <Button variant="destructive" @click="emit('reset-to-chain', event.id)">Réinitialiser</Button>
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
                            <Button variant="destructive" @click="emit('detach-event', event.id)">Détacher</Button>
                        </template>
                    </AppAlertDialog>
                </TooltipTrigger>
                <TooltipContent><p>Dissocier cet événement de la série</p></TooltipContent>
            </Tooltip>
        </div>
    </div>
</template>
