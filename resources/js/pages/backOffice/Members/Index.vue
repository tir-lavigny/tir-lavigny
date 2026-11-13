<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Member, PaginatedMembers, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Eye, Plus, RefreshCw, Undo2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Membres', href: route('bo.members.index') }];

const props = withDefaults(
    defineProps<{
        members: PaginatedMembers;
        filters?: { search?: string; status?: string; archived?: boolean };
    }>(),
    { filters: () => ({}) },
);

const { filters, visit } = useListFilters('bo.members.index', {
    search: props.filters.search || '',
    status: props.filters.status || '',
    archived: props.filters.archived ? '1' : '',
});

const statusFilter = computed({
    get: () => filters.value.status || 'all',
    set: (value: string) => {
        filters.value.status = value === 'all' ? '' : value;
    },
});

const archivedFilter = computed({
    get: () => filters.value.archived === '1',
    set: (value: boolean) => {
        filters.value.archived = value ? '1' : '';
    },
});

const handleRestore = (member: Member) => {
    useForm({}).post(route('bo.members.restore', member.id), {
        onSuccess: () => toast.success('Membre restauré.'),
        onError: () => toast.error('Erreur lors de la restauration.'),
    });
};

const handleSync = (member: Member) => {
    useForm({}).post(route('bo.members.sync', member.id), {
        onSuccess: () => toast.success('Synchronisation effectuée.'),
        onError: () => toast.error('Erreur lors de la synchronisation.'),
    });
};
</script>

<template>
    <Head title="Membres" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex w-full flex-col items-center justify-between gap-6 lg:flex-row">
                <div class="relative w-full items-center lg:max-w-sm">
                    <Input v-model="filters.search" placeholder="Rechercher..." class="pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search class="text-muted-foreground size-6" />
                    </span>
                </div>
                <div class="flex w-full items-center gap-2 lg:w-auto">
                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-full lg:w-48">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous les statuts</SelectItem>
                            <SelectItem value="active">Actif</SelectItem>
                            <SelectItem value="passive">Passif</SelectItem>
                        </SelectContent>
                    </Select>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox v-model="archivedFilter" />
                        Archivés
                    </label>
                    <Button v-if="hasPermissionTo(user, 'manage members')" @click="router.get(route('bo.members.create'))">
                        <Plus class="mr-2 h-4 w-4" /> Nouveau membre
                    </Button>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Membres</H3>
                    </div>
                </div>
                <div
                    v-for="member in members.data"
                    :key="member.id"
                    class="mb-4 flex flex-col gap-3 rounded-lg border p-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ member.first_name }} {{ member.last_name }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="member.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ member.status === 'active' ? 'Actif' : 'Passif' }}
                            </span>
                            <span v-if="member.deleted_at" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">Archivé</span>
                        </div>
                        <Muted>{{ member.email_masked || '—' }}</Muted>
                        <Muted v-if="member.dav_sync_error" class="text-red-600">Synchronisation DAV en erreur</Muted>
                    </div>
                    <div class="flex items-center gap-2">
                        <Tooltip>
                            <TooltipTrigger>
                                <Link :href="route('bo.members.show', member.id)">
                                    <Button size="icon" variant="outline"><Eye /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Voir</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="hasPermissionTo(user, 'manage members')">
                            <TooltipTrigger>
                                <Button size="icon" variant="outline" @click="handleSync(member)"><RefreshCw /></Button>
                            </TooltipTrigger>
                            <TooltipContent><p>Synchroniser</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="member.deleted_at && hasPermissionTo(user, 'manage members')">
                            <TooltipTrigger>
                                <Button size="icon" variant="outline" @click="handleRestore(member)"><Undo2 /></Button>
                            </TooltipTrigger>
                            <TooltipContent><p>Restaurer</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <Muted v-if="members.data?.length" class="my-4 flex items-center justify-center"> {{ members.total }} membre(s) trouvé(s) </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucun membre trouvé</Muted>
                <AppPagination
                    :items-per-page="members.per_page"
                    :total-items="members.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :page="members.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="visit"
                />
            </div>
        </div>
    </AppLayout>
</template>
