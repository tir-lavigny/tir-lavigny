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
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

defineProps<{
    locations: StockLocation[];
    products: Product[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pertes', href: route('bo.stock.losses.index') },
    { title: 'Enregistrer', href: route('bo.stock.losses.create') },
];

const form = useForm({
    location_id: '',
    product_id: '',
    quantity: 1,
    reason: '',
});

const handleSubmit = () => {
    if (!form.location_id) {
        toast.error('Veuillez sélectionner un lieu.');
        return;
    }
    form.post(route('bo.stock.losses.store', form.location_id), {
        onError: () => toast.error("Erreur lors de l'enregistrement."),
    });
};
</script>

<template>
    <Head title="Enregistrer une perte" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Enregistrer une perte</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!form.isDirty" @click="handleSubmit"><Save /></Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Enregistrer la perte</p></TooltipContent>
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
                    <div class="grid gap-2">
                        <Label for="location_id">Lieu de stockage</Label>
                        <Select v-model="form.location_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un lieu" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="location in locations" :key="location.id" :value="String(location.id)">
                                    {{ location.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.location_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="product_id">Produit</Label>
                        <Select v-model="form.product_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un produit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="product in products" :key="product.id" :value="String(product.id)">
                                    {{ product.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.product_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quantity">Quantité perdue</Label>
                        <Input id="quantity" v-model="form.quantity" type="number" min="1" required />
                        <InputError :message="form.errors.quantity" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="reason">Raison (optionnel)</Label>
                        <Textarea
                            id="reason"
                            v-model="form.reason"
                            placeholder="Ex: Bouteilles cassées, Produits périmés..."
                            maxlength="1000"
                            rows="3"
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
