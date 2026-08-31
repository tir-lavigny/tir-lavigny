<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedSeasons, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Saisons', href: route('bo.register.seasons.index') }];

const props = withDefaults(
    defineProps<{
        seasons: PaginatedSeasons;
        filters?: { search?: string };
    }>(),
    { filters: () => ({}) },
);

const filters = ref({ search: props.filters.search || '' });

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.register.seasons.index'), { page: p, search: filters.value.search }, { preserveState: true, preserveScroll: true });
};

watch(
    filters,
    (f) => {
        router.get(route('bo.register.seasons.index'), { page: 1, search: f.search }, { preserveState: true, preserveScroll: true });
    },
    { deep: true },
);

const handleDelete = (id: number) => {
    useForm({}).delete(route('bo.register.seasons.destroy', id), {
        onSuccess: () => toast.success('Saison supprimée.'),
        onError: () => toast.error('Erreur lors de la suppression.'),
    });
};
</script>

<template>
    <Head title="Saisons" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex w-full flex-col items-center justify-between gap-6 lg:flex-row">
                <div class="relative w-full items-center lg:max-w-sm">
                    <Input v-model="filters.search" placeholder="Rechercher..." class="pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search class="text-muted-foreground size-6" />
                    </span>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Saisons</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage seasons')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.seasons.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Créer une saison</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <div v-for="season in seasons.data" :key="season.id" class="mb-4 flex items-center justify-between rounded-lg border p-4">
                    <Link :href="route('bo.register.seasons.show', season.id)" class="flex-1">
                        <p class="font-medium hover:underline">{{ season.name }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ formatDate(season.start_date, 'DD.MM.YYYY') }} — {{ formatDate(season.end_date, 'DD.MM.YYYY') }}
                        </p>
                    </Link>
                    <div class="flex gap-2">
                        <Tooltip v-if="hasPermissionTo(user, 'manage seasons')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.seasons.edit', season.id)">
                                    <Button size="icon" variant="outline"><Pencil /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Modifier</p></TooltipContent>
                        </Tooltip>
                        <AppAlertDialog
                            v-if="hasPermissionTo(user, 'manage seasons')"
                            title="Supprimer cette saison ?"
                            description="Cette action est irréversible."
                            cancelText="Annuler"
                        >
                            <template #trigger>
                                <Button size="icon" variant="destructive"><Trash2 /></Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleDelete(season.id)">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </div>
                </div>
                <Muted v-if="seasons.data?.length" class="my-4 flex items-center justify-center"> {{ seasons.total }} saison(s) trouvée(s) </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucune saison trouvée</Muted>
                <AppPagination
                    :items-per-page="seasons.per_page"
                    :total-items="seasons.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="seasons.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
