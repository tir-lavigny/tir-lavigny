<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Product, StockLocation } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Minus, Plus, Save, Undo2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

defineProps<{
    locations: StockLocation[];
    products: Product[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transferts', href: route('bo.stock.transfers.index') },
    { title: 'Créer', href: route('bo.stock.transfers.create') },
];

const form = useForm({
    from_location_id: '',
    to_location_id: '',
    items: [{ product_id: '', quantity: 1 }] as { product_id: string; quantity: number }[],
    reason: '',
});

const selectedProducts = computed(() => {
    return form.items.map((item) => item.product_id).filter(Boolean);
});

function addRow() {
    form.items.push({ product_id: '', quantity: 1 });
}

function removeRow(index: number) {
    form.items.splice(index, 1);
}

const handleSubmit = () => {
    if (!form.from_location_id) {
        toast.error("Veuillez sélectionner un lieu d'origine.");
        return;
    }
    form.post(route('bo.stock.transfers.store', form.from_location_id), {
        onError: () => toast.error('Erreur lors de la création.'),
    });
};
</script>

<template>
    <Head title="Créer un transfert" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Créer un transfert</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!form.isDirty" @click="handleSubmit"><Save /></Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Créer le transfert</p></TooltipContent>
                </Tooltip>
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" variant="destructive" :disabled="!form.isDirty" @click="form.reset()"><Undo2 /></Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Annuler</p></TooltipContent>
                </Tooltip>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                <H3>Informations</H3>
                <div class="mt-6 flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="from_location_id">Lieu d'origine</Label>
                            <Select v-model="form.from_location_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un lieu" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="location in locations" :key="location.id" :value="String(location.id)">
                                        {{ location.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.from_location_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="to_location_id">Lieu de destination</Label>
                            <Select v-model="form.to_location_id" :disabled="!form.from_location_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un lieu" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="location in locations"
                                        :key="location.id"
                                        :value="String(location.id)"
                                        :disabled="location.id === Number(form.from_location_id)"
                                    >
                                        {{ location.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.to_location_id" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <H3>Articles</H3>
                    <Button size="sm" variant="outline" @click="addRow"><Plus class="mr-1 size-4" /> Ajouter</Button>
                </div>
                <div class="mt-6 flex flex-col gap-4">
                    <div v-for="(item, index) in form.items" :key="index" class="flex items-end gap-4">
                        <div class="grid flex-1 gap-2">
                            <Label v-if="index === 0" :for="`product_${index}`">Produit</Label>
                            <Select v-model="item.product_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un produit" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="product in products"
                                        :key="product.id"
                                        :value="String(product.id)"
                                        :disabled="selectedProducts.includes(String(product.id)) && product.id !== Number(item.product_id)"
                                    >
                                        {{ product.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors[`items.${index}.product_id`]" />
                        </div>
                        <div class="grid w-28 gap-2">
                            <Label v-if="index === 0" :for="`quantity_${index}`">Quantité</Label>
                            <Input :id="`quantity_${index}`" v-model="item.quantity" type="number" min="1" required />
                            <InputError :message="form.errors[`items.${index}.quantity`]" />
                        </div>
                        <Button size="icon" variant="destructive" :disabled="form.items.length <= 1" @click="removeRow(index)">
                            <Minus class="size-4" />
                        </Button>
                    </div>
                </div>
                <InputError :message="form.errors.items" />
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                <H3>Raison (optionnel)</H3>
                <div class="mt-6">
                    <Textarea v-model="form.reason" placeholder="Raison du transfert..." maxlength="1000" rows="3" />
                    <InputError :message="form.errors.reason" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
