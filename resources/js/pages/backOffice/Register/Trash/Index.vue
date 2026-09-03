<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type {
    BreadcrumbItem,
    PaginatedCategories,
    PaginatedMembers,
    PaginatedProducts,
    PaginatedSeasons,
    PaginatedTickets,
    PaginatedVouchers,
} from '@/types';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { RotateCcw, Trash2, AlertTriangle } from '@lucide/vue';
import AppPagination from '@/components/AppPagination.vue';
import { formatDate } from '@/lib/utils';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Caisse', href: '#' },
    { title: 'Corbeille', href: route('bo.register.trash.index') },
];

interface CascadeChild {
    type: string;
    count: number;
    label: string;
}

interface MemberOption {
    id: number;
    first_name: string;
    last_name: string;
}

const props = defineProps<{
    categories: PaginatedCategories;
    products: PaginatedProducts;
    seasons: PaginatedSeasons;
    members: PaginatedMembers;
    tickets: PaginatedTickets;
    vouchers: PaginatedVouchers;
    memberFilter: number | null;
    allMembers: MemberOption[];
}>();

const activeTab = ref('categories');
const selectedMemberId = ref<string>(props.memberFilter ? String(props.memberFilter) : '');

const restoreDialog = ref({
    open: false,
    type: '',
    id: 0,
    label: '',
    willRestore: [] as CascadeChild[],
    processing: false,
});

const forceDeleteDialog = ref({
    open: false,
    type: '',
    id: 0,
    label: '',
    processing: false,
});

const confirmRestore = () => {
    restoreDialog.value.processing = true;
    useForm({ confirm: true }).post(route('bo.register.trash.restore', { type: restoreDialog.value.type, id: restoreDialog.value.id }), {
        onSuccess: () => {
            toast.success('Élément restauré avec succès.');
            restoreDialog.value.open = false;
            router.reload({ only: ['categories', 'products', 'seasons', 'members', 'tickets', 'vouchers'] });
        },
        onError: () => {
            toast.error('Erreur lors de la restauration.');
        },
        onFinish: () => {
            restoreDialog.value.processing = false;
        },
    });
};

const confirmForceDelete = () => {
    forceDeleteDialog.value.processing = true;
    useForm({}).delete(route('bo.register.trash.force-delete', { type: forceDeleteDialog.value.type, id: forceDeleteDialog.value.id }), {
        onSuccess: () => {
            toast.success('Élément supprimé définitivement.');
            forceDeleteDialog.value.open = false;
            router.reload({ only: ['categories', 'products', 'seasons', 'members', 'tickets', 'vouchers'] });
        },
        onError: (errors) => {
            toast.error(errors.message || 'Erreur lors de la suppression.');
            forceDeleteDialog.value.open = false;
        },
        onFinish: () => {
            forceDeleteDialog.value.processing = false;
        },
    });
};

const openRestoreDialog = async (type: string, id: number, label: string) => {
    try {
        const response = await fetch(route('bo.register.trash.restore', { type, id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
            body: JSON.stringify({}),
        });
        const data = await response.json();
        restoreDialog.value = {
            open: true,
            type,
            id,
            label,
            willRestore: data.will_restore || [],
            processing: false,
        };
    } catch {
        toast.error('Erreur lors du chargement des informations de restauration.');
    }
};

const openForceDeleteDialog = (type: string, id: number, label: string) => {
    forceDeleteDialog.value = {
        open: true,
        type,
        id,
        label,
        processing: false,
    };
};

const handlePageChange = (tab: string, page: number) => {
    const routeName = 'bo.register.trash.index';
    const params: Record<string, any> = {};
    params[`${tab}_page`] = page;
    if (selectedMemberId.value) {
        params.member_id = selectedMemberId.value;
    }
    router.get(route(routeName), params, { preserveState: true, preserveScroll: true });
};

const handleMemberFilterChange = () => {
    const params: Record<string, any> = {};
    if (selectedMemberId.value) {
        params.member_id = selectedMemberId.value;
    }
    router.get(route('bo.register.trash.index'), params, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Corbeille" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex items-center justify-between">
                    <h1 class="text-xl font-semibold">Corbeille</h1>
                    <Badge variant="secondary">Éléments supprimés</Badge>
                </div>

                <Tabs v-model="activeTab">
                    <TabsList>
                        <TabsTrigger value="categories">Catégories ({{ categories.total }})</TabsTrigger>
                        <TabsTrigger value="products">Produits ({{ products.total }})</TabsTrigger>
                        <TabsTrigger value="seasons">Saisons ({{ seasons.total }})</TabsTrigger>
                        <TabsTrigger value="members">Membres ({{ members.total }})</TabsTrigger>
                        <TabsTrigger value="tickets">Tickets ({{ tickets.total }})</TabsTrigger>
                        <TabsTrigger value="vouchers">Bons ({{ vouchers.total }})</TabsTrigger>
                    </TabsList>

                    <TabsContent value="categories">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nom</TableHead>
                                    <TableHead>Supprimé le</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in categories.data" :key="item.id">
                                    <TableCell class="font-medium">{{ item.name }}</TableCell>
                                    <TableCell>{{ formatDate(item.deleted_at ?? '', 'DD.MM.YYYY') }}</TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" @click="openRestoreDialog('category', item.id, item.name)">
                                                <RotateCcw class="mr-1 h-4 w-4" /> Restaurer
                                            </Button>
                                            <Button size="sm" variant="destructive" @click="openForceDeleteDialog('category', item.id, item.name)">
                                                <Trash2 class="mr-1 h-4 w-4" /> Supprimer
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="categories.data?.length === 0">
                                    <TableCell colspan="3" class="text-muted-foreground text-center">Aucune catégorie supprimée</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div class="mt-4">
                            <AppPagination
                                :total-items="categories.total"
                                :items-per-page="categories.per_page"
                                :default-page="categories.current_page"
                                @page-changed="(p) => handlePageChange('categories', p)"
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="products">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nom</TableHead>
                                    <TableHead>Catégorie</TableHead>
                                    <TableHead>Supprimé le</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in products.data" :key="item.id">
                                    <TableCell class="font-medium">{{ item.name }}</TableCell>
                                    <TableCell>{{ item.category?.name ?? '—' }}</TableCell>
                                    <TableCell>{{ formatDate(item.deleted_at ?? '', 'DD.MM.YYYY') }}</TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" @click="openRestoreDialog('product', item.id, item.name)">
                                                <RotateCcw class="mr-1 h-4 w-4" /> Restaurer
                                            </Button>
                                            <Button size="sm" variant="destructive" @click="openForceDeleteDialog('product', item.id, item.name)">
                                                <Trash2 class="mr-1 h-4 w-4" /> Supprimer
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="products.data?.length === 0">
                                    <TableCell colspan="4" class="text-muted-foreground text-center">Aucun produit supprimé</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div class="mt-4">
                            <AppPagination
                                :total-items="products.total"
                                :items-per-page="products.per_page"
                                :default-page="products.current_page"
                                @page-changed="(p) => handlePageChange('products', p)"
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="seasons">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nom</TableHead>
                                    <TableHead>Supprimé le</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in seasons.data" :key="item.id">
                                    <TableCell class="font-medium">{{ item.name }}</TableCell>
                                    <TableCell>{{ formatDate(item.deleted_at ?? '', 'DD.MM.YYYY') }}</TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" @click="openRestoreDialog('season', item.id, item.name)">
                                                <RotateCcw class="mr-1 h-4 w-4" /> Restaurer
                                            </Button>
                                            <Button size="sm" variant="destructive" @click="openForceDeleteDialog('season', item.id, item.name)">
                                                <Trash2 class="mr-1 h-4 w-4" /> Supprimer
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="seasons.data?.length === 0">
                                    <TableCell colspan="3" class="text-muted-foreground text-center">Aucune saison supprimée</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div class="mt-4">
                            <AppPagination
                                :total-items="seasons.total"
                                :items-per-page="seasons.per_page"
                                :default-page="seasons.current_page"
                                @page-changed="(p) => handlePageChange('seasons', p)"
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="members">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nom</TableHead>
                                    <TableHead>Supprimé le</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in members.data" :key="item.id">
                                    <TableCell class="font-medium">{{ item.first_name }} {{ item.last_name }}</TableCell>
                                    <TableCell>{{ formatDate(item.deleted_at ?? '', 'DD.MM.YYYY') }}</TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="openRestoreDialog('member', item.id, item.first_name + ' ' + item.last_name)"
                                            >
                                                <RotateCcw class="mr-1 h-4 w-4" /> Restaurer
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="openForceDeleteDialog('member', item.id, item.first_name + ' ' + item.last_name)"
                                            >
                                                <Trash2 class="mr-1 h-4 w-4" /> Supprimer
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="members.data?.length === 0">
                                    <TableCell colspan="3" class="text-muted-foreground text-center">Aucun membre supprimé</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div class="mt-4">
                            <AppPagination
                                :total-items="members.total"
                                :items-per-page="members.per_page"
                                :default-page="members.current_page"
                                @page-changed="(p) => handlePageChange('members', p)"
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="tickets">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>#</TableHead>
                                    <TableHead>Journée</TableHead>
                                    <TableHead>Membre</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Supprimé le</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in tickets.data" :key="item.id">
                                    <TableCell class="font-medium">#{{ item.id }}</TableCell>
                                    <TableCell>{{ item.operating_day?.date ?? '—' }}</TableCell>
                                    <TableCell>{{ item.member ? item.member.first_name + ' ' + item.member.last_name : '—' }}</TableCell>
                                    <TableCell>{{ item.total }} CHF</TableCell>
                                    <TableCell>{{ formatDate(item.deleted_at ?? '', 'DD.MM.YYYY') }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            @click="openForceDeleteDialog('ticket', item.id, 'Ticket #' + item.id)"
                                        >
                                            <Trash2 class="mr-1 h-4 w-4" /> Supprimer
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="tickets.data?.length === 0">
                                    <TableCell colspan="6" class="text-muted-foreground text-center">Aucun ticket supprimé</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div class="mt-4">
                            <AppPagination
                                :total-items="tickets.total"
                                :items-per-page="tickets.per_page"
                                :default-page="tickets.current_page"
                                @page-changed="(p) => handlePageChange('tickets', p)"
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="vouchers">
                        <div class="mb-4 flex items-center gap-4">
                            <label class="text-sm font-medium">Filtrer par membre :</label>
                            <select
                                v-model="selectedMemberId"
                                class="border-input focus-visible:ring-ring flex h-9 w-full max-w-xs rounded-md border bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                @change="handleMemberFilterChange"
                            >
                                <option value="">Tous les membres</option>
                                <option v-for="m in allMembers" :key="m.id" :value="m.id">{{ m.first_name }} {{ m.last_name }}</option>
                            </select>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>#</TableHead>
                                    <TableHead>Membre</TableHead>
                                    <TableHead>Produit</TableHead>
                                    <TableHead>Réduction</TableHead>
                                    <TableHead>Supprimé le</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in vouchers.data" :key="item.id">
                                    <TableCell class="font-medium">#{{ item.id }}</TableCell>
                                    <TableCell>{{ item.member ? item.member.first_name + ' ' + item.member.last_name : '—' }}</TableCell>
                                    <TableCell>{{ item.product?.name ?? '—' }}</TableCell>
                                    <TableCell>{{ item.discount_percent ? item.discount_percent + '%' : item.discount_fixed + ' CHF' }}</TableCell>
                                    <TableCell>{{ formatDate(item.deleted_at ?? '', 'DD.MM.YYYY') }}</TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" @click="openRestoreDialog('voucher', item.id, 'Bon #' + item.id)">
                                                <RotateCcw class="mr-1 h-4 w-4" /> Restaurer
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="openForceDeleteDialog('voucher', item.id, 'Bon #' + item.id)"
                                            >
                                                <Trash2 class="mr-1 h-4 w-4" /> Supprimer
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="vouchers.data?.length === 0">
                                    <TableCell colspan="6" class="text-muted-foreground text-center">Aucun bon supprimé</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div class="mt-4">
                            <AppPagination
                                :total-items="vouchers.total"
                                :items-per-page="vouchers.per_page"
                                :default-page="vouchers.current_page"
                                @page-changed="(p) => handlePageChange('vouchers', p)"
                            />
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <!-- Restore Confirmation Dialog -->
        <Dialog v-model:open="restoreDialog.open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Restaurer cet élément ?</DialogTitle>
                    <DialogDescription>
                        Vous êtes sur le point de restaurer <strong>{{ restoreDialog.label }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="restoreDialog.willRestore.length > 0" class="mt-4">
                    <Alert>
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>Effet de cascade</AlertTitle>
                        <AlertDescription>
                            <ul class="mt-2 list-disc pl-4">
                                <li v-for="(item, index) in restoreDialog.willRestore" :key="index">Restaurera aussi {{ item.label }}</li>
                            </ul>
                        </AlertDescription>
                    </Alert>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="restoreDialog.open = false">Annuler</Button>
                    <Button @click="confirmRestore" :disabled="restoreDialog.processing">
                        <RotateCcw class="mr-1 h-4 w-4" /> Confirmer la restauration
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Force Delete Confirmation Dialog -->
        <Dialog v-model:open="forceDeleteDialog.open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Supprimer définitivement ?</DialogTitle>
                    <DialogDescription>
                        Vous êtes sur le point de supprimer définitivement <strong>{{ forceDeleteDialog.label }}</strong
                        >. Cette action est irréversible.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="forceDeleteDialog.open = false">Annuler</Button>
                    <Button variant="destructive" @click="confirmForceDelete" :disabled="forceDeleteDialog.processing">
                        <Trash2 class="mr-1 h-4 w-4" /> Supprimer définitivement
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
