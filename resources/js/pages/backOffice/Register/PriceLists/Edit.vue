<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PriceListForm from '@/components/back-office/Register/PriceListForm.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PriceList, Product, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{ priceList: PriceList; products: Product[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Listes de prix', href: route('bo.register.price-lists.index') },
    { title: props.priceList.name, href: route('bo.register.price-lists.edit', props.priceList.id) },
];

const initialItems: Record<number, number | null> = {};
(props.priceList.items ?? []).forEach((item) => {
    initialItems[item.product_id] = Number(item.unit_price);
});

const form = useForm({
    name: props.priceList.name,
    notes: props.priceList.notes ?? '',
    items: initialItems,
});

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'manage products')) {
        toast.error("Vous n'avez pas les droits.");
        return;
    }

    const items = Object.entries(form.items)
        .filter(([, price]) => price !== null)
        .map(([product_id, unit_price]) => ({ product_id: Number(product_id), unit_price: Number(unit_price) }));

    form.transform((data) => ({ ...data, items })).put(route('bo.register.price-lists.update', props.priceList.id), {
        onSuccess: () => toast.success('Liste de prix mise à jour.'),
        onError: () => toast.error('Erreur lors de la mise à jour.'),
    });
};
</script>

<template>
    <Head :title="'Modifier ' + priceList.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Modifier {{ priceList.name }}</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!form.isDirty" @click="handleSubmit"><Save /></Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Enregistrer</p></TooltipContent>
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
                        <Label for="name">Nom</Label>
                        <Input id="name" v-model="form.name" type="text" placeholder="Nom de la liste" maxlength="255" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <Textarea id="notes" v-model="form.notes" placeholder="Notes optionnelles" />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                <H3>Prix</H3>
                <PriceListForm v-model:items="form.items" :products="products" :errors="form.errors" class="mt-6" />
            </div>
        </div>
    </AppLayout>
</template>
