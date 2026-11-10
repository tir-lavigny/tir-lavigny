<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney } from '@/lib/utils';
import type { BreadcrumbItem, PriceList, PriceListPreview, PriceListPreviewRow } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Play } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{ priceList: PriceList; preview: PriceListPreview }>();

const processing = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Listes de prix', href: route('bo.register.price-lists.index') },
    { title: props.priceList.name, href: route('bo.register.price-lists.apply.preview', props.priceList.id) },
];

const statusLabel = (status: PriceListPreviewRow['status']): string => {
    switch (status) {
        case 'changed':
            return 'Modifié';
        case 'unchanged':
            return 'Inchangé';
        case 'missing':
            return 'Produit supprimé';
        case 'not_in_list':
            return 'Hors liste';
    }
};

const statusVariant = (status: PriceListPreviewRow['status']): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'changed':
            return 'default';
        case 'unchanged':
            return 'secondary';
        case 'missing':
            return 'destructive';
        case 'not_in_list':
            return 'outline';
    }
};

const handleApply = () => {
    if (processing.value) {
        return;
    }

    router.post(
        route('bo.register.price-lists.apply', props.priceList.id),
        {},
        {
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => toast.success('Prix appliqués.'),
            onError: () => toast.error("Erreur lors de l'application."),
        },
    );
};
</script>

<template>
    <Head :title="'Appliquer ' + priceList.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Appliquer {{ priceList.name }}</H1>
            <div class="flex flex-row gap-2">
                <Tooltip>
                    <TooltipTrigger>
                        <Link :href="route('bo.register.price-lists.index')">
                            <Button variant="outline" size="icon"><ArrowLeft /></Button>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent><p>Retour</p></TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger>
                        <Button size="icon" :disabled="processing" @click="handleApply"><Play /></Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Appliquer les prix</p></TooltipContent>
                </Tooltip>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                <H3>Résumé</H3>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Badge variant="default">{{ preview.summary.changed }} prix à modifier</Badge>
                    <Badge variant="secondary">{{ preview.summary.unchanged }} prix inchangé(s)</Badge>
                    <Badge variant="destructive">{{ preview.summary.missing }} produit(s) supprimé(s)</Badge>
                    <Badge variant="outline">{{ preview.summary.not_in_list }} produit(s) hors liste</Badge>
                </div>
                <Muted v-if="priceList.last_applied_at" class="mt-2 text-xs">
                    Dernière application : {{ formatDate(priceList.last_applied_at, 'DD.MM.YYYY HH:mm') }}
                </Muted>
                <Muted class="mt-2 text-xs">
                    Les produits absents de la liste conservent leur prix actuel. Les produits supprimés sont ignorés.
                </Muted>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                <H3>Changements</H3>
                <Table class="mt-4">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Produit</TableHead>
                            <TableHead>Catégorie</TableHead>
                            <TableHead>Prix actuel</TableHead>
                            <TableHead>Nouveau prix</TableHead>
                            <TableHead>Statut</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in preview.rows" :key="row.product_id">
                            <TableCell>{{ row.product_name }}</TableCell>
                            <TableCell>{{ row.category_name ?? '—' }}</TableCell>
                            <TableCell>{{ row.current_price === null ? '—' : formatMoney(row.current_price) + ' CHF' }}</TableCell>
                            <TableCell>{{ row.new_price === null ? '—' : formatMoney(row.new_price) + ' CHF' }}</TableCell>
                            <TableCell
                                ><Badge :variant="statusVariant(row.status)">{{ statusLabel(row.status) }}</Badge></TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
                <Muted v-if="!preview.rows.length" class="my-4 flex items-center justify-center">Aucun changement à afficher.</Muted>
            </div>
        </div>
    </AppLayout>
</template>
