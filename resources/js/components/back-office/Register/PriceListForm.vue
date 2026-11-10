<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatMoney } from '@/lib/utils';
import type { Product } from '@/types';
import { computed, ref } from 'vue';

const props = defineProps<{
    products: Product[];
    items: Record<number, number | null>;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:items': [items: Record<number, number | null>];
}>();

const search = ref('');
const categoryFilter = ref('all');

const categories = computed(() => {
    const names = new Set(props.products.map((product) => product.category?.name ?? 'Sans catégorie'));

    return Array.from(names).sort();
});

const filteredProducts = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.products.filter((product) => {
        const matchesSearch = !term || product.name.toLowerCase().includes(term);
        const categoryName = product.category?.name ?? 'Sans catégorie';
        const matchesCategory = categoryFilter.value === 'all' || categoryName === categoryFilter.value;

        return matchesSearch && matchesCategory;
    });
});

const groups = computed(() => {
    const grouped = new Map<string, Product[]>();

    filteredProducts.value.forEach((product) => {
        const categoryName = product.category?.name ?? 'Sans catégorie';
        grouped.set(categoryName, [...(grouped.get(categoryName) ?? []), product]);
    });

    return Array.from(grouped.entries()).map(([name, products]) => ({ name, products }));
});

const updatePrice = (productId: number, value: string | number) => {
    const next = { ...props.items };

    if (value === '' || value === null) {
        delete next[productId];
    } else {
        next[productId] = Number(value);
    }

    emit('update:items', next);
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2 lg:flex-row">
            <Input v-model="search" placeholder="Rechercher un produit..." class="lg:max-w-sm" />
            <Select v-model="categoryFilter">
                <SelectTrigger class="lg:w-64">
                    <SelectValue placeholder="Toutes les catégories" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Toutes les catégories</SelectItem>
                    <SelectItem v-for="name in categories" :key="name" :value="name">{{ name }}</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <InputError :message="errors?.items" />
        <div v-for="group in groups" :key="group.name" class="flex flex-col gap-2">
            <H3>{{ group.name }}</H3>
            <div v-for="product in group.products" :key="product.id" class="flex items-center justify-between gap-4 rounded-lg border p-3">
                <div class="flex flex-col">
                    <span class="font-medium">{{ product.name }}</span>
                    <Muted class="text-sm">Prix actuel : {{ formatMoney(product.unit_price) }} CHF</Muted>
                </div>
                <div class="flex items-center gap-2">
                    <Input
                        :model-value="items[product.id] ?? ''"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Non défini"
                        class="w-32"
                        @update:model-value="updatePrice(product.id, $event)"
                    />
                    <span class="text-muted-foreground text-sm">CHF</span>
                </div>
            </div>
        </div>
        <Muted v-if="!groups.length">Aucun produit trouvé.</Muted>
    </div>
</template>
