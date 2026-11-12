<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedProducts, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Produits', href: route('bo.register.products.index') }];

const props = withDefaults(
    defineProps<{
        products: PaginatedProducts;
        filters?: { search?: string };
    }>(),
    { filters: () => ({}) },
);

const { filters, visit } = useListFilters('bo.register.products.index', {
    search: props.filters.search || '',
});

const handleDelete = (id: number) => {
    useForm({}).delete(route('bo.register.products.destroy', id), {
        onSuccess: () => toast.success('Produit supprimé.'),
        onError: () => toast.error('Erreur lors de la suppression.'),
    });
};
</script>

<template>
    <Head title="Produits" />
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
                        <H3>Produits</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.products.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Créer un produit</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <div v-for="product in products.data" :key="product.id" class="mb-4 flex items-center justify-between rounded-lg border p-4">
                    <Link :href="route('bo.register.products.show', product.id)" class="flex-1">
                        <p class="font-medium hover:underline">{{ product.name }}</p>
                        <div class="text-muted-foreground flex items-center gap-2 text-sm">
                            <span>{{ formatMoney(product.unit_price) }} CHF</span>
                            <span v-if="product.category">· {{ product.category.name }}</span>
                            <Badge v-if="product.allows_custom_price" variant="secondary" class="text-xs">Prix libre</Badge>
                            <Badge v-if="product.is_stockable" variant="secondary" class="text-xs">Stockable</Badge>
                            <Badge v-if="product.name === 'Correction'" variant="outline" class="text-xs">Protégé</Badge>
                        </div>
                    </Link>
                    <div class="flex gap-2">
                        <Tooltip>
                            <TooltipTrigger>
                                <Link :href="route('bo.register.products.show', product.id)">
                                    <Button size="icon" variant="outline"><Eye /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Voir</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.products.edit', product.id)">
                                    <Button size="icon" variant="outline"><Pencil /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Modifier</p></TooltipContent>
                        </Tooltip>
                        <AppAlertDialog
                            v-if="hasPermissionTo(user, 'manage products') && product.name !== 'Correction'"
                            title="Supprimer ce produit ?"
                            description="Cette action est irréversible."
                            cancelText="Annuler"
                        >
                            <template #trigger>
                                <Button size="icon" variant="destructive"><Trash2 /></Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleDelete(product.id)">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </div>
                </div>
                <Muted v-if="products.data?.length" class="my-4 flex items-center justify-center"> {{ products.total }} produit(s) trouvé(s) </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucun produit trouvé</Muted>
                <AppPagination
                    :items-per-page="products.per_page"
                    :total-items="products.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :page="products.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="visit"
                />
            </div>
        </div>
    </AppLayout>
</template>
