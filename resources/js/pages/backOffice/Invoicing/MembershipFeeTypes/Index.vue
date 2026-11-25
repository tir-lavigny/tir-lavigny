<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H1 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedMembershipFeeTypes, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    feeTypes: PaginatedMembershipFeeTypes;
    filters: { search?: string | null; member_status?: string | null; active?: string | null };
    memberStatuses: Array<{ value: string; label: string }>;
}>();

const page = usePage<SharedData>();
const canManage = computed(() => hasAnyPermission(page.props.auth.user, ['manage membership fees', 'manage invoicing']));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Types de cotisation', href: route('bo.invoicing.membership-fee-types.index') }];

const { filters } = useListFilters('bo.invoicing.membership-fee-types.index', {
    search: props.filters.search || '',
    member_status: props.filters.member_status || '',
    active: props.filters.active || '',
});

const memberStatusFilter = computed({
    get: () => filters.value.member_status || 'all',
    set: (value: string) => {
        filters.value.member_status = value === 'all' ? '' : value;
    },
});

const activeFilter = computed({
    get: () => filters.value.active || 'all',
    set: (value: string) => {
        filters.value.active = value === 'all' ? '' : value;
    },
});

const memberStatusLabel = (value: string | null): string => props.memberStatuses.find((status) => status.value === value)?.label ?? '—';

const goToPage = (pageNumber: number) => {
    router.get(
        route('bo.invoicing.membership-fee-types.index'),
        { page: pageNumber, ...filters.value },
        { preserveState: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Types de cotisation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <H1>Types de cotisation</H1>
                <p class="text-muted-foreground mt-2 text-sm">Un type actif est requis pour chaque statut de membre lors de l’aperçu annuel.</p>
            </div>
            <Button v-if="canManage" as-child>
                <Link :href="route('bo.invoicing.membership-fee-types.create')"> <Plus class="mr-2 h-4 w-4" /> Nouveau type </Link>
            </Button>
        </div>

        <div class="mx-4 flex flex-col gap-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid flex-1 gap-2">
                    <Label for="search">Rechercher</Label>
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2" />
                        <Input id="search" v-model="filters.search" class="pl-8" placeholder="Nom du type" />
                    </div>
                </div>
                <div class="grid gap-2 md:w-56">
                    <Label for="member_status">Statut</Label>
                    <Select v-model="memberStatusFilter">
                        <SelectTrigger id="member_status" class="w-full">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="status in memberStatuses" :key="status.value" :value="status.value">{{ status.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:w-44">
                    <Label for="active">État</Label>
                    <Select v-model="activeFilter">
                        <SelectTrigger id="active" class="w-full">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem value="active">Actifs</SelectItem>
                            <SelectItem value="inactive">Inactifs</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nom</th>
                            <th class="px-4 py-3 text-left font-medium">Statut</th>
                            <th class="px-4 py-3 text-right font-medium">Montant</th>
                            <th class="px-4 py-3 text-left font-medium">État</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="feeType in feeTypes.data" :key="feeType.id" class="border-sidebar-border/70 dark:border-sidebar-border border-t">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ feeType.name }}</div>
                                <div v-if="feeType.description" class="text-muted-foreground">{{ feeType.description }}</div>
                            </td>
                            <td class="px-4 py-3">{{ memberStatusLabel(feeType.member_status) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(feeType.amount) }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="feeType.is_active ? 'secondary' : 'outline'">{{ feeType.is_active ? 'Actif' : 'Inactif' }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="canManage" size="sm" variant="outline" as-child>
                                    <Link :href="route('bo.invoicing.membership-fee-types.edit', feeType.id)">
                                        <Pencil class="mr-2 h-4 w-4" /> Modifier
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!feeTypes.data?.length">
                            <td colspan="5" class="text-muted-foreground px-4 py-8 text-center">Aucun type de cotisation configuré.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination :items-per-page="feeTypes.per_page" :total-items="feeTypes.total" :page="feeTypes.current_page" @page-changed="goToPage" />
        </div>
    </AppLayout>
</template>
