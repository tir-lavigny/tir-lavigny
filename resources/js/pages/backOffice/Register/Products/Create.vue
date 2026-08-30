<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Category, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

defineProps<{ categories: Category[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produits', href: route('bo.register.products.index') },
    { title: 'Créer', href: route('bo.register.products.create') },
];

const form = useForm({ name: '', unit_price: 0, category_id: '', allows_custom_price: false });

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'manage products')) {
        toast.error("Vous n'avez pas les droits.");
        return;
    }
    form.post(route('bo.register.products.store'), {
        onSuccess: () => {
            form.reset();
            toast.success('Produit créé.');
        },
        onError: () => toast.error('Erreur lors de la création.'),
    });
};
</script>

<template>
    <Head title="Créer un produit" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Créer un produit</H1>
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
                        <Input id="name" v-model="form.name" type="text" placeholder="Nom du produit" maxlength="255" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="unit_price">Prix unitaire (CHF)</Label>
                            <Input id="unit_price" v-model="form.unit_price" type="number" step="0.01" min="0" required />
                            <InputError :message="form.errors.unit_price" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="category_id">Catégorie</Label>
                            <Select v-model="form.category_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner une catégorie" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cat in categories" :key="cat.id" :value="String(cat.id)">
                                        {{ cat.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category_id" />
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch id="allows_custom_price" v-model:checked="form.allows_custom_price" />
                        <Label for="allows_custom_price">Prix libre (permet un prix personnalisé)</Label>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
