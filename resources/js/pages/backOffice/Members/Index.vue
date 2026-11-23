<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Member, PaginatedMembers, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Download, Eye, Plus, RefreshCw, Search, Undo2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
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
    router.post(
        route('bo.members.restore', member.id),
        {},
        {
            onError: () => toast.error('Erreur lors de la restauration.'),
        },
    );
};

const handleSync = (member: Member) => {
    router.post(
        route('bo.members.sync', member.id),
        {},
        {
            onError: () => toast.error('Erreur lors de la synchronisation.'),
        },
    );
};

const showExportDialog = ref(false);
const exportFormat = ref('xlsx');
const exportSortByStatus = ref(false);
const exportColumns = ref<string[]>(['last_name', 'first_name', 'email', 'tel', 'status']);
const extraColumns = ref<{ header: string; width: number | null }[]>([]);

const exportColumnOptions = [
    { key: 'first_name', label: 'Prénom' },
    { key: 'last_name', label: 'Nom' },
    { key: 'email', label: 'Email' },
    { key: 'tel', label: 'Téléphone' },
    { key: 'street', label: 'Rue' },
    { key: 'npa', label: 'NPA' },
    { key: 'city', label: 'Ville' },
    { key: 'canton', label: 'Canton' },
    { key: 'country', label: 'Pays' },
    { key: 'status', label: 'Statut' },
];

const sensitiveColumnOptions = [
    { key: 'avs', label: 'AVS (numéro complet)' },
    { key: 'is_swiss_citizen', label: 'Nationalité suisse' },
];

const canExportSensitiveData = computed(() => hasPermissionTo(user, 'view member sensitive data'));
const exportsSensitiveData = computed(() => exportColumns.value.some((column) => sensitiveColumnOptions.some((option) => option.key === column)));

const toggleExportColumn = (key: string) => {
    exportColumns.value = exportColumns.value.includes(key) ? exportColumns.value.filter((column) => column !== key) : [...exportColumns.value, key];
};

const columnLabels: Record<string, string> = Object.fromEntries([...exportColumnOptions, ...sensitiveColumnOptions].map((option) => [option.key, option.label]));

const columnLabel = (key: string): string => columnLabels[key] ?? key;

const moveItem = <T,>(items: T[], from: number, to: number): T[] => {
    const next = [...items];
    const [moved] = next.splice(from, 1);

    if (moved === undefined) {
        return next;
    }

    next.splice(to, 0, moved);

    return next;
};

const moveExportColumn = (key: string, offset: number) => {
    const from = exportColumns.value.indexOf(key);
    const to = from + offset;

    if (from === -1 || to < 0 || to >= exportColumns.value.length) {
        return;
    }

    exportColumns.value = moveItem(exportColumns.value, from, to);
};

const moveExtraColumn = (index: number, offset: number) => {
    const to = index + offset;

    if (to < 0 || to >= extraColumns.value.length) {
        return;
    }

    extraColumns.value = moveItem(extraColumns.value, index, to);
};

const addExtraColumn = () => {
    if (extraColumns.value.length >= 20) {
        return;
    }

    extraColumns.value.push({ header: '', width: null });
};

const removeExtraColumn = (index: number) => {
    extraColumns.value.splice(index, 1);
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    params.set('format', exportFormat.value);

    if (filters.value.search) {
        params.set('search', filters.value.search);
    }
    if (filters.value.status) {
        params.set('status', filters.value.status);
    }
    if (filters.value.archived === '1') {
        params.set('archived', '1');
    }
    if (exportSortByStatus.value) {
        params.set('sort', 'status');
    }

    exportColumns.value.forEach((column) => params.append('columns[]', column));

    extraColumns.value
        .filter((extra) => extra.header.trim() !== '')
        .forEach((extra, index) => {
            params.set(`extra_columns[${index}][header]`, extra.header.trim());

            if (typeof extra.width === 'number' && extra.width >= 1) {
                params.set(`extra_columns[${index}][width]`, String(extra.width));
            }
        });

    return `${route('bo.members.export')}?${params.toString()}`;
});

const downloadExport = () => {
    if (exportColumns.value.length === 0) {
        toast.error('Sélectionnez au moins une colonne à exporter.');

        return;
    }

    const link = document.createElement('a');
    link.href = exportUrl.value;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    showExportDialog.value = false;
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
                    <Button variant="outline" @click="showExportDialog = true"> <Download class="mr-2 h-4 w-4" /> Exporter </Button>
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
                            <Badge
                                :class="
                                    member.status === 'active'
                                        ? 'border-emerald-200 bg-emerald-100 text-emerald-800'
                                        : 'border-amber-200 bg-amber-100 text-amber-800'
                                "
                            >
                                {{ member.status === 'active' ? 'Actif' : 'Passif' }}
                            </Badge>
                            <Badge v-if="member.deleted_at" variant="outline" class="bg-gray-100 text-gray-700">Archivé</Badge>
                            <Badge v-if="member.anonymized_at" variant="outline" class="bg-gray-100 text-gray-700">Anonymisé</Badge>
                        </div>
                        <Muted>{{ member.email || '—' }}</Muted>
                        <Badge v-if="member.dav_sync_error" variant="outline" class="w-fit border-red-200 bg-red-100 text-red-800">
                            Synchronisation DAV en erreur
                        </Badge>
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
                        <Tooltip v-if="!member.anonymized_at && hasPermissionTo(user, 'manage members')">
                            <TooltipTrigger>
                                <Button size="icon" variant="outline" @click="handleSync(member)"><RefreshCw /></Button>
                            </TooltipTrigger>
                            <TooltipContent><p>Synchroniser</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="member.deleted_at && !member.anonymized_at && hasPermissionTo(user, 'manage members')">
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

        <Dialog v-model:open="showExportDialog">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Exporter les membres</DialogTitle>
                    <DialogDescription>
                        L'export reprend les filtres actifs (recherche, statut, archivés). Choisissez le format et les colonnes à inclure.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Format</Label>
                        <Select v-model="exportFormat">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="xlsx">Excel (.xlsx)</SelectItem>
                                <SelectItem value="csv">CSV (.csv)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Colonnes</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <label v-for="column in exportColumnOptions" :key="column.key" class="flex items-center gap-2 text-sm">
                                <Checkbox :model-value="exportColumns.includes(column.key)" @update:model-value="toggleExportColumn(column.key)" />
                                {{ column.label }}
                            </label>
                        </div>
                        <template v-if="canExportSensitiveData">
                            <div
                                v-if="exportsSensitiveData"
                                class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                            >
                                Vous exportez des données personnelles sensibles (AVS, nationalité). Stockez le fichier de façon sécurisée et
                                supprimez-le une fois utilisé.
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <label v-for="column in sensitiveColumnOptions" :key="column.key" class="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        :model-value="exportColumns.includes(column.key)"
                                        @update:model-value="toggleExportColumn(column.key)"
                                    />
                                    {{ column.label }}
                                </label>
                            </div>
                        </template>
                        <p v-else class="text-muted-foreground text-xs">
                            L'export de l'AVS et de la nationalité nécessite la permission « view member sensitive data ».
                        </p>
                    </div>
                    <div v-if="exportColumns.length > 0" class="grid gap-2">
                        <Label>Ordre des colonnes</Label>
                        <ol class="grid gap-1">
                            <li v-for="(key, index) in exportColumns" :key="key" class="flex items-center gap-2 text-sm">
                                <span class="text-muted-foreground w-4 text-right tabular-nums">{{ index + 1 }}.</span>
                                <span class="flex-1">{{ columnLabel(key) }}</span>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :disabled="index === 0"
                                    aria-label="Monter la colonne"
                                    @click="moveExportColumn(key, -1)"
                                >
                                    <ChevronUp />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :disabled="index === exportColumns.length - 1"
                                    aria-label="Descendre la colonne"
                                    @click="moveExportColumn(key, 1)"
                                >
                                    <ChevronDown />
                                </Button>
                            </li>
                        </ol>
                        <p class="text-muted-foreground text-xs">Les colonnes sont écrites dans cet ordre, de gauche à droite.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox v-model="exportSortByStatus" />
                        Trier par statut (actifs puis passifs)
                    </label>
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label>Colonnes supplémentaires</Label>
                            <Button type="button" size="sm" variant="outline" @click="addExtraColumn"> <Plus class="mr-2 size-4" /> Ajouter </Button>
                        </div>
                        <p class="text-muted-foreground text-xs">
                            Colonnes vides ajoutées après les données du membre. Laissez la largeur vide pour l'ajuster au contenu (Excel
                            uniquement).
                        </p>
                        <div v-for="(extra, index) in extraColumns" :key="index" class="flex items-center gap-2">
                            <Input v-model="extra.header" :placeholder="`En-tête ${index + 1}`" maxlength="100" />
                            <Input
                                v-model.number="extra.width"
                                type="number"
                                min="1"
                                max="255"
                                placeholder="auto"
                                class="w-24"
                                aria-label="Largeur (auto si vide)"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === 0"
                                aria-label="Monter la colonne"
                                @click="moveExtraColumn(index, -1)"
                            >
                                <ChevronUp />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === extraColumns.length - 1"
                                aria-label="Descendre la colonne"
                                @click="moveExtraColumn(index, 1)"
                            >
                                <ChevronDown />
                            </Button>
                            <Button type="button" size="icon" variant="outline" @click="removeExtraColumn(index)">
                                <X />
                            </Button>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showExportDialog = false">Annuler</Button>
                    <Button @click="downloadExport"><Download class="mr-2 size-4" /> Exporter</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
