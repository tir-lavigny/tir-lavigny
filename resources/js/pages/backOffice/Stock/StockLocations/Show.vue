<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedStockMovements, SharedData, StockLocation, StockLocationStock, StockSnapshot } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ClipboardList, Edit, Forklift, History, WineOff } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    location: StockLocation;
    stock: StockLocationStock[];
    movements: PaginatedStockMovements;
    snapshots: StockSnapshot[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Emplacements', href: route('bo.stock.locations.index') },
    { title: props.location.name, href: route('bo.stock.locations.show', props.location.id) },
];

const movementTypeLabel: Record<string, string> = {
    initial: 'Initial',
    purchase: 'Achat',
    sale: 'Vente',
    return: 'Retour',
    transfer: 'Transfert',
    loss: 'Perte',
    adjustment: 'Ajustement',
    reversal: 'Annulation',
};

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.stock.locations.show', props.location.id), { page: p }, { preserveState: true, preserveScroll: true });
};

const stockableItems = computed(() => props.stock.filter((item) => item.quantity !== 0 || true));

const handleCompact = () => {
    router.post(route('bo.stock.locations.compact', props.location.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="location.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <div class="flex items-center gap-3">
                <H1>{{ location.name }}</H1>
                <Badge :variant="location.active ? 'secondary' : 'destructive'">
                    {{ location.active ? 'Actif' : 'Inactif' }}
                </Badge>
            </div>
            <Link v-if="hasPermissionTo(user, 'manage stock')" :href="route('bo.stock.locations.edit', location.id)">
                <Button variant="outline" size="sm">
                    <Edit class="mr-1 size-4" />
                    Modifier
                </Button>
            </Link>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Détails</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Nom</dt>
                            <dd class="font-medium">{{ location.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Statut</dt>
                            <dd class="font-medium">{{ location.active ? 'Actif' : 'Inactif' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-muted-foreground text-sm">Description</dt>
                            <dd class="font-medium">{{ location.description ?? '—' }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <div v-if="location.active" class="flex flex-wrap gap-2">
                <Link :href="route('bo.stock.losses.create')">
                    <Button variant="outline" size="sm">
                        <WineOff class="mr-1 size-4" />
                        Enregistrer une perte
                    </Button>
                </Link>
                <Link :href="route('bo.stock.transfers.create')">
                    <Button variant="outline" size="sm">
                        <Forklift class="mr-1 size-4" />
                        Transférer du stock
                    </Button>
                </Link>
                <Link :href="route('bo.stock.counts.create')">
                    <Button variant="outline" size="sm">
                        <ClipboardList class="mr-1 size-4" />
                        Inventaire
                    </Button>
                </Link>
            </div>

            <H3>Stock actuel</H3>
            <Table v-if="stockableItems.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Produit</TableHead>
                        <TableHead class="text-right">Quantité</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="item in stockableItems" :key="item.product_id">
                        <TableCell class="font-medium">{{ item.product.name }}</TableCell>
                        <TableCell class="text-right">
                            <span :class="item.quantity < 0 ? 'text-red-600' : item.quantity > 0 ? 'text-green-600' : ''">
                                {{ item.quantity }}
                            </span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="text-muted-foreground text-sm">Aucun stock actuel.</p>

            <H3>Historique des mouvements</H3>
            <Table v-if="movements.data?.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Produit</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead class="text-right">Quantité</TableHead>
                        <TableHead>Raison</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="movement in movements.data" :key="movement.id">
                        <TableCell>{{ formatDate(movement.created_at, 'DD.MM.YYYY HH:mm') }}</TableCell>
                        <TableCell class="font-medium">{{ movement.product?.name ?? '-' }}</TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{ movementTypeLabel[movement.type] ?? movement.type }}</Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <span :class="movement.quantity < 0 ? 'text-red-600' : movement.quantity > 0 ? 'text-green-600' : ''">
                                {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                            </span>
                        </TableCell>
                        <TableCell class="text-muted-foreground max-w-[200px] truncate text-sm">{{ movement.reason ?? '—' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="text-muted-foreground text-sm">Aucun mouvement.</p>
            <Muted v-if="movements.data?.length" class="flex items-center justify-center"> {{ movements.total }} mouvement(s) </Muted>
            <AppPagination
                :items-per-page="movements.per_page"
                :total-items="movements.total"
                :sibling-count="1"
                :show-edges="true"
                :default-page="movements.current_page"
                :show-prev-next="true"
                :show-first-last="false"
                @page-changed="handlePageChange"
            />

            <template v-if="hasPermissionTo(user, 'manage stock')">
                <H3>Snapshots de stock</H3>
                <div class="flex items-center gap-2">
                    <AlertDialog>
                        <AlertDialogTrigger as-child>
                            <Button variant="outline" size="sm">
                                <History class="mr-1 size-4" />
                                Compacter l'historique
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Compacter l'historique des mouvements ?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Cette opération va créer un snapshot du stock actuel et archiver les mouvements antérieurs.
                                    Le stock physique ne changera pas. Les mouvements récents seront conservés.
                                    Cette action est destinée à réduire la taille de l'historique.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Annuler</AlertDialogCancel>
                                <AlertDialogAction @click="handleCompact" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                    Compacter
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>

                <Table v-if="snapshots.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date du snapshot</TableHead>
                            <TableHead>Produits capturés</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="snapshot in snapshots" :key="snapshot.id">
                            <TableCell>{{ formatDate(snapshot.snapshot_at, 'DD.MM.YYYY HH:mm') }}</TableCell>
                            <TableCell>{{ snapshot.stock_snapshot_lines?.length ?? 0 }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else class="text-muted-foreground text-sm">Aucun snapshot.</p>
            </template>
        </div>
    </AppLayout>
</template>
