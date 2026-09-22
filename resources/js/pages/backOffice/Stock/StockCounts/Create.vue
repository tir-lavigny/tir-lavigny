<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, StockLocation } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

defineProps<{
    locations: StockLocation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaires', href: route('bo.stock.counts.index') },
    { title: 'Créer', href: route('bo.stock.counts.create') },
];

const form = useForm({
    stock_location_id: '',
    type: '',
});

const handleSubmit = () => {
    form.post(route('bo.stock.counts.store'), {
        onError: () => toast.error('Erreur lors de la création.'),
    });
};
</script>

<template>
    <Head title="Créer un inventaire" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Créer un inventaire</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!form.isDirty" @click="handleSubmit"><Save /></Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Créer l'inventaire</p></TooltipContent>
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
                <p class="text-muted-foreground mt-2 text-sm">
                    L'inventaire sera créé avec toutes les produits stockables. Les quantités physiques seront ensuite à saisir sur la page de détail.
                </p>
                <div class="mt-6 flex flex-col gap-4">
                    <div class="grid gap-2">
                        <Label for="stock_location_id">Lieu de stockage</Label>
                        <Select v-model="form.stock_location_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un lieu" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="location in locations" :key="location.id" :value="String(location.id)">
                                    {{ location.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.stock_location_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="type">Type d'inventaire</Label>
                        <Select v-model="form.type">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="initial">Initial</SelectItem>
                                <SelectItem value="adjustment">Ajustement</SelectItem>
                                <SelectItem value="final">Final</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
