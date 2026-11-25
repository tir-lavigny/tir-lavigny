<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppTooltipIconButton from '@/components/AppTooltipIconButton.vue';
import AppTooltipLabel from '@/components/AppTooltipLabel.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedMembershipFeeCampaigns, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    campaigns: PaginatedMembershipFeeCampaigns;
    filters: { search?: string | null; status?: string | null; year?: number | null };
    statuses: Array<{ value: string; label: string }>;
    years: number[];
}>();

const page = usePage<SharedData>();
const canManage = computed(() => hasAnyPermission(page.props.auth.user, ['manage membership fees', 'manage invoicing']));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Campagnes de cotisation', href: route('bo.invoicing.membership-fee-campaigns.index') }];

const { filters } = useListFilters('bo.invoicing.membership-fee-campaigns.index', {
    search: props.filters.search || '',
    status: props.filters.status || '',
    year: props.filters.year ? String(props.filters.year) : '',
});

const statusFilter = computed({
    get: () => filters.value.status || 'all',
    set: (value: string) => {
        filters.value.status = value === 'all' ? '' : value;
    },
});

const yearFilter = computed({
    get: () => filters.value.year || 'all',
    set: (value: string) => {
        filters.value.year = value === 'all' ? '' : value;
    },
});

const statusLabel = (value: string): string => props.statuses.find((status) => status.value === value)?.label ?? value;

const statusDescription = (value: string): string => {
    const descriptions: Record<string, string> = {
        draft: 'La campagne est créée mais aucun aperçu n’a encore été généré.',
        preview: 'Des membres sont en attente de validation ou de correction.',
        validated: 'Les membres sont validés. Les factures brouillon peuvent être générées.',
        invoiced: 'Les factures brouillon existent mais ne sont pas encore émises.',
        issued: 'Toutes les factures de la campagne ont été émises.',
        cancelled: 'Cette campagne est annulée.',
    };

    return descriptions[value] ?? value;
};

const deleteCampaign = (campaign: { id: number }): void => {
    router.delete(route('bo.invoicing.membership-fee-campaigns.destroy', campaign.id), {
        preserveScroll: true,
        onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Erreur lors de la suppression.'),
    });
};

const deleteDescription = (campaign: { draft_invoices_count?: number }): string => {
    const draftCount = campaign.draft_invoices_count ?? 0;

    if (draftCount > 0) {
        return `La campagne et ses ${draftCount} facture(s) brouillon seront supprimées. Les clients de facturation seront conservés.`;
    }

    return 'La campagne et ses lignes seront supprimées. Les clients de facturation seront conservés.';
};

const goToPage = (pageNumber: number) => {
    router.get(
        route('bo.invoicing.membership-fee-campaigns.index'),
        { page: pageNumber, ...filters.value },
        { preserveState: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Campagnes de cotisation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Campagnes de cotisation</H1>
                <p class="text-muted-foreground mt-2 text-sm">Une campagne par année. Les montants sont figés dans chaque ligne de campagne.</p>
            </div>
            <Button v-if="canManage" as-child>
                <Link :href="route('bo.invoicing.membership-fee-campaigns.create')"> <Plus class="mr-2 h-4 w-4" /> Nouvelle campagne </Link>
            </Button>
        </div>

        <div class="mx-4 flex flex-col gap-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid flex-1 gap-2">
                    <Label for="search">Rechercher</Label>
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2" />
                        <Input id="search" v-model="filters.search" class="pl-8" placeholder="Nom de la campagne" />
                    </div>
                </div>
                <div class="grid gap-2 md:w-48">
                    <Label for="status">Statut campagne</Label>
                    <Select v-model="statusFilter">
                        <SelectTrigger id="status" class="w-full">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:w-36">
                    <Label for="year">Année</Label>
                    <Select v-model="yearFilter">
                        <SelectTrigger id="year" class="w-full">
                            <SelectValue placeholder="Toutes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Toutes</SelectItem>
                            <SelectItem v-for="year in years" :key="year" :value="String(year)">{{ year }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Année</th>
                            <th class="px-4 py-3 text-left font-medium">Campagne</th>
                            <th class="px-4 py-3 text-left font-medium">Statut campagne</th>
                            <th class="px-4 py-3 text-left font-medium">Date d’émission</th>
                            <th class="px-4 py-3 text-left font-medium">Date d’échéance</th>
                            <th class="px-4 py-3 text-right font-medium">
                                <div class="flex justify-end">
                                    <AppTooltipLabel label="Membres" description="Nombre total de membres dans la campagne, exclusions comprises." />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="campaign in campaigns.data"
                            :key="campaign.id"
                            class="border-sidebar-border/70 dark:border-sidebar-border border-t"
                        >
                            <td class="px-4 py-3 font-mono font-medium">{{ campaign.year }}</td>
                            <td class="px-4 py-3">{{ campaign.name }}</td>
                            <td class="px-4 py-3">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Badge variant="secondary" class="cursor-help">{{ statusLabel(campaign.status) }}</Badge>
                                    </TooltipTrigger>
                                    <TooltipContent>{{ statusDescription(campaign.status) }}</TooltipContent>
                                </Tooltip>
                            </td>
                            <td class="px-4 py-3">{{ formatDate(campaign.issue_date, 'DD.MM.YYYY') }}</td>
                            <td class="px-4 py-3">{{ formatDate(campaign.due_date, 'DD.MM.YYYY') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ campaign.members_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" as-child>
                                        <Link :href="route('bo.invoicing.membership-fee-campaigns.show', campaign.id)">
                                            <Eye class="mr-2 h-4 w-4" /> Ouvrir
                                        </Link>
                                    </Button>
                                    <AppTooltipIconButton
                                        v-if="canManage && ['draft', 'preview', 'validated'].includes(campaign.status)"
                                        label="Modifier la campagne"
                                        variant="outline"
                                        :href="route('bo.invoicing.membership-fee-campaigns.edit', campaign.id)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </AppTooltipIconButton>
                                    <AppAlertDialog
                                        v-if="canManage && (campaign.non_draft_invoices_count ?? 0) === 0"
                                        title="Supprimer la campagne ?"
                                        :description="deleteDescription(campaign)"
                                        cancel-text="Annuler"
                                    >
                                        <template #trigger>
                                            <AppTooltipIconButton label="Supprimer la campagne" variant="destructive">
                                                <Trash2 class="h-4 w-4" />
                                            </AppTooltipIconButton>
                                        </template>
                                        <template #action>
                                            <Button variant="destructive" @click="deleteCampaign(campaign)">Supprimer définitivement</Button>
                                        </template>
                                    </AppAlertDialog>
                                    <AppTooltipIconButton
                                        v-else-if="canManage"
                                        label="Suppression impossible : la campagne contient une facture émise."
                                        variant="destructive"
                                        disabled
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </AppTooltipIconButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!campaigns.data?.length">
                            <td colspan="7" class="text-muted-foreground px-4 py-8 text-center">Aucune campagne créée.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination
                :items-per-page="campaigns.per_page"
                :total-items="campaigns.total"
                :page="campaigns.current_page"
                @page-changed="goToPage"
            />
        </div>
    </AppLayout>
</template>
