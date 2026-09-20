<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, StockCount, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    count: StockCount;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaires', href: route('bo.stock.counts.index') },
    { title: `Inventaire #${props.count.id}`, href: route('bo.stock.counts.show', props.count.id) },
];

const isOpen = computed(() => !props.count.completed_at);

const countTypeLabel: Record<string, string> = {
    initial: 'Initial',
    adjustment: 'Ajustement',
    final: 'Final',
};

const linesForm = useForm({
    lines: (props.count.stock_count_lines ?? []).map((line) => ({
        product_id: line.product_id,
        counted_quantity: line.counted_quantity ?? '',
    })),
});

const handleComplete = () => {
    linesForm.post(route('bo.stock.counts.complete', props.count.id), {
        onError: () => toast.error('Erreur lors de la complétion.'),
    });
};

function getDifference(lineIndex: number): number | null {
    const line = linesForm.lines[lineIndex];
    if (line.counted_quantity === '' || line.counted_quantity === null || line.counted_quantity === undefined) {
        return null;
    }
    const expected = props.count.stock_count_lines?.[lineIndex]?.expected_quantity ?? 0;
    return Number(line.counted_quantity) - expected;
}
</script>

<template>
    <Head :title="`Inventaire #${count.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <div class="flex items-center gap-3">
                <H1>Inventaire #{{ count.id }}</H1>
                <Badge :variant="isOpen ? 'default' : 'secondary'">{{ isOpen ? 'En cours' : 'Terminé' }}</Badge>
            </div>
            <Tooltip v-if="isOpen && hasPermissionTo(user, 'manage stock')">
                <TooltipTrigger>
                    <Button @click="handleComplete" :disabled="linesForm.processing">
                        <CheckCircle class="mr-2 size-4" /> Terminer l'inventaire
                    </Button>
                </TooltipTrigger>
                <TooltipContent><p>Marquer l'inventaire comme terminé</p></TooltipContent>
            </Tooltip>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Détails</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Lieu</dt>
                            <dd class="font-medium">{{ count.stock_location?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Type</dt>
                            <dd class="font-medium">{{ countTypeLabel[count.type] ?? count.type }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Créé le</dt>
                            <dd class="font-medium">{{ formatDate(count.created_at, 'DD.MM.YYYY HH:mm') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Complété le</dt>
                            <dd class="font-medium">
                                {{ count.completed_at ? formatDate(count.completed_at, 'DD.MM.YYYY HH:mm') : '-' }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <H3>Lignes d'inventaire</H3>

            <Table v-if="count.stock_count_lines?.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Produit</TableHead>
                        <TableHead class="text-right">Quantité attendue</TableHead>
                        <TableHead class="text-right">Quantité comptée</TableHead>
                        <TableHead class="text-right">Écart</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="isOpen">
                        <TableRow v-for="(line, index) in linesForm.lines" :key="line.product_id">
                            <TableCell class="font-medium">{{ count.stock_count_lines?.[index]?.product?.name ?? '-' }}</TableCell>
                            <TableCell class="text-right">{{ count.stock_count_lines?.[index]?.expected_quantity ?? 0 }}</TableCell>
                            <TableCell class="text-right">
                                <Input v-model="line.counted_quantity" type="number" class="w-24 text-right" placeholder="0" />
                                <InputError :message="linesForm.errors[`lines.${index}.counted_quantity`]" />
                            </TableCell>
                            <TableCell class="text-right">
                                <span
                                    v-if="getDifference(index) !== null"
                                    :class="getDifference(index)! > 0 ? 'text-green-600' : getDifference(index)! < 0 ? 'text-red-600' : ''"
                                >
                                    {{ getDifference(index)! > 0 ? '+' : '' }}{{ getDifference(index) }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow v-for="line in count.stock_count_lines" :key="line.id">
                            <TableCell class="font-medium">{{ line.product?.name ?? '-' }}</TableCell>
                            <TableCell class="text-right">{{ line.expected_quantity }}</TableCell>
                            <TableCell class="text-right">{{ line.counted_quantity ?? '-' }}</TableCell>
                            <TableCell class="text-right">
                                <span
                                    v-if="line.counted_quantity !== null"
                                    :class="
                                        line.counted_quantity - line.expected_quantity > 0
                                            ? 'text-green-600'
                                            : line.counted_quantity - line.expected_quantity < 0
                                              ? 'text-red-600'
                                              : ''
                                    "
                                >
                                    {{ line.counted_quantity - line.expected_quantity > 0 ? '+' : ''
                                    }}{{ line.counted_quantity - line.expected_quantity }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
            <p v-else class="text-muted-foreground text-sm">Aucune ligne d'inventaire.</p>
        </div>
    </AppLayout>
</template>
