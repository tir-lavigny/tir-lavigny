<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H3, H4 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PriceList, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, Pencil, Play, Plus, RefreshCw, RotateCw, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{ priceLists: PriceList[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Listes de prix', href: route('bo.register.price-lists.index') }];

const handleDelete = (id: number) => {
    router.delete(route('bo.register.price-lists.destroy', id), {
        onSuccess: () => toast.success('Liste de prix supprimée.'),
        onError: () => toast.error('Erreur lors de la suppression.'),
    });
};

const handleDuplicate = (id: number) => {
    router.post(
        route('bo.register.price-lists.duplicate', id),
        {},
        {
            onSuccess: () => toast.success('Liste de prix dupliquée.'),
            onError: () => toast.error('Erreur lors de la duplication.'),
        },
    );
};

const handleSync = (id: number) => {
    router.post(
        route('bo.register.price-lists.sync-from-catalog', id),
        {},
        {
            onSuccess: () => toast.success('Liste synchronisée.'),
            onError: () => toast.error('Erreur lors de la synchronisation.'),
        },
    );
};

const handleSyncPrices = (id: number) => {
    router.post(
        route('bo.register.price-lists.sync-prices', id),
        {},
        {
            onSuccess: () => toast.success('Prix de la liste synchronisés.'),
            onError: () => toast.error('Erreur lors de la synchronisation des prix.'),
        },
    );
};
</script>

<template>
    <Head title="Listes de prix" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row items-center justify-between">
                    <H3>Listes de prix</H3>
                    <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                        <TooltipTrigger>
                            <Link :href="route('bo.register.price-lists.create')">
                                <Button size="icon"><Plus /></Button>
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent><p>Créer une liste de prix</p></TooltipContent>
                    </Tooltip>
                </div>
                <div v-for="priceList in props.priceLists" :key="priceList.id" class="mb-4 flex flex-col gap-3 rounded-lg border p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <H4 class="text-lg">{{ priceList.name }}</H4>
                                <Badge v-if="priceList.is_active" variant="default">Active</Badge>
                                <Badge variant="secondary">{{ priceList.items_count ?? 0 }} produit(s)</Badge>
                            </div>
                            <Muted v-if="priceList.notes">{{ priceList.notes }}</Muted>
                            <Muted v-if="priceList.last_applied_at" class="text-xs">
                                Dernière application : {{ formatDate(priceList.last_applied_at, 'DD.MM.YYYY HH:mm') }}
                            </Muted>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-if="priceList.drift?.changed" variant="default">{{ priceList.drift.changed }} prix à appliquer</Badge>
                            <Badge v-if="priceList.drift?.missing" variant="destructive">{{ priceList.drift.missing }} produit(s) supprimé(s)</Badge>
                            <Badge v-if="priceList.drift?.not_in_list" variant="outline">{{ priceList.drift.not_in_list }} hors liste</Badge>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Tooltip>
                            <TooltipTrigger>
                                <Link :href="route('bo.register.price-lists.apply.preview', priceList.id)">
                                    <Button variant="outline" size="sm"><Play /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Voir les changements avant application</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.price-lists.edit', priceList.id)">
                                    <Button variant="outline" size="sm"><Pencil /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Modifier la liste</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                            <TooltipTrigger>
                                <Button variant="outline" size="sm" @click="handleDuplicate(priceList.id)"><Copy /></Button>
                            </TooltipTrigger>
                            <TooltipContent><p>Dupliquer la liste</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                            <TooltipTrigger>
                                <AppAlertDialog
                                    title="Synchroniser la structure de cette liste ?"
                                    description="Les produits manquants seront ajoutés avec leur prix actuel et les produits supprimés seront retirés."
                                    cancelText="Annuler"
                                >
                                    <template #trigger>
                                        <Button variant="outline" size="sm"><RefreshCw /></Button>
                                    </template>
                                    <template #action>
                                        <Button variant="default" @click="handleSync(priceList.id)">Synchroniser</Button>
                                    </template>
                                </AppAlertDialog>
                            </TooltipTrigger>
                            <TooltipContent><p>Ajouter les produits manquants et retirer les produits supprimés</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                            <TooltipTrigger>
                                <AppAlertDialog
                                    title="Synchroniser les prix de cette liste ?"
                                    description="Les prix de la liste seront remplacés par les prix actuels des produits. Les produits supprimés sont ignorés."
                                    cancelText="Annuler"
                                >
                                    <template #trigger>
                                        <Button variant="outline" size="sm"><RotateCw /></Button>
                                    </template>
                                    <template #action>
                                        <Button variant="default" @click="handleSyncPrices(priceList.id)">Synchroniser</Button>
                                    </template>
                                </AppAlertDialog>
                            </TooltipTrigger>
                            <TooltipContent><p>Mettre à jour les prix de la liste avec les prix actuels</p></TooltipContent>
                        </Tooltip>
                        <AppAlertDialog
                            v-if="hasPermissionTo(user, 'manage products')"
                            title="Supprimer cette liste de prix ?"
                            description="Cette action est irréversible."
                            cancelText="Annuler"
                        >
                            <template #trigger>
                                <Button variant="destructive" size="sm"><Trash2 /></Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleDelete(priceList.id)">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </div>
                </div>
                <Muted v-if="!props.priceLists.length" class="my-4 flex items-center justify-center">Aucune liste de prix trouvée</Muted>
            </div>
        </div>
    </AppLayout>
</template>
