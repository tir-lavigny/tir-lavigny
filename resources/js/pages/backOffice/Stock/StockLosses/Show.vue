<script setup lang="ts">
import { H1 } from '@/components/typography/headings';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, StockLoss } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    loss: StockLoss;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pertes', href: route('bo.stock.losses.index') },
    { title: `Perte #${props.loss.id}`, href: route('bo.stock.losses.show', props.loss.id) },
];
</script>

<template>
    <Head :title="`Perte #${loss.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Perte #{{ loss.id }}</H1>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Détails</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Date</dt>
                            <dd class="font-medium">{{ formatDate(loss.created_at, 'DD.MM.YYYY HH:mm') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Lieu</dt>
                            <dd class="font-medium">{{ loss.stock_location?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Produit</dt>
                            <dd class="font-medium">{{ loss.product?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Quantité</dt>
                            <dd class="font-medium">{{ loss.quantity }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-muted-foreground text-sm">Raison</dt>
                            <dd class="font-medium">{{ loss.reason ?? '-' }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
