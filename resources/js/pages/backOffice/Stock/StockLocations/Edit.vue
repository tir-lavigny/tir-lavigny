<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import Label from '@/components/ui/label/Label.vue';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, StockLocation } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    location: StockLocation;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Emplacements', href: route('bo.stock.locations.index') },
    { title: props.location.name, href: route('bo.stock.locations.show', props.location.id) },
    { title: 'Modifier', href: route('bo.stock.locations.edit', props.location.id) },
];

const form = useForm({
    name: props.location.name,
    description: props.location.description ?? '',
    active: props.location.active,
});

const handleSubmit = () => {
    form.put(route('bo.stock.locations.update', props.location.id), {
        onError: () => toast.error('Erreur lors de la mise à jour.'),
    });
};
</script>

<template>
    <Head :title="`Modifier ${location.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Modifier {{ location.name }}</H1>
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
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Nom de l'emplacement"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Description de l'emplacement..."
                            maxlength="1000"
                            rows="3"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch id="active" v-model="form.active" />
                        <Label for="active">Actif</Label>
                        <InputError :message="form.errors.active" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
