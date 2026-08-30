<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedCategories, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { useForm } from '@inertiajs/vue3';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catégories', href: route('bo.register.categories.index') },
];

const props = defineProps<{
    categories: PaginatedCategories;
    filters: { search?: string };
}>();

const filters = ref({ search: props.filters.search || '' });

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.register.categories.index'), { page: p, search: filters.value.search }, { preserveState: true, preserveScroll: true });
};

watch(filters, (f) => {
    router.get(route('bo.register.categories.index'), { page: 1, search: f.search }, { preserveState: true, preserveScroll: true });
}, { deep: true });

const handleDelete = (id: number) => {
    const form = useForm({});
    form.delete(route('bo.register.categories.destroy', id), {
        onSuccess: () => toast.success('Catégorie supprimée.'),
        onError: () => toast.error('Erreur lors de la suppression.'),
    });
};
</script>

<template>
    <Head title="Catégories" />
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
                        <H3>Catégories</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage categories')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.categories.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Créer une catégorie</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <div v-for="category in categories.data" :key="category.id" class="mb-4 flex items-center justify-between rounded-lg border p-4">
                    <div>
                        <p class="font-medium">{{ category.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ category.is_restricted ? 'Restreinte' : 'Publique' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Tooltip v-if="hasPermissionTo(user, 'manage categories')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.categories.edit', category.id)">
                                    <Button size="icon" variant="outline"><Pencil /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Modifier</p></TooltipContent>
                        </Tooltip>
                        <AppAlertDialog v-if="hasPermissionTo(user, 'manage categories')" title="Supprimer cette catégorie ?" description="Cette action est irréversible.">
                            <template #trigger>
                                <Button size="icon" variant="destructive"><Trash2 /></Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleDelete(category.id)">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </div>
                </div>
                <Muted v-if="categories.data?.length" class="my-4 flex items-center justify-center">
                    {{ categories.total }} catégorie(s) trouvée(s)
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucune catégorie trouvée</Muted>
                <AppPagination
                    :items-per-page="categories.per_page"
                    :total-items="categories.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="categories.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
