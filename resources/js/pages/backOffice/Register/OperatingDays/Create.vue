<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();

const props = defineProps<{
    seasons: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journées', href: route('bo.register.operating-days.index') },
    { title: 'Ouvrir', href: route('bo.register.operating-days.create') },
];

const form = useForm({
    season_id: '',
    date: new Date().toISOString().split('T')[0],
});

const handleSubmit = () => {
    form.post(route('bo.register.operating-days.store'), {
        onSuccess: () => {
            toast.success('Journée ouverte avec succès.');
        },
        onError: () => toast.error("Erreur lors de l'ouverture de la journée."),
    });
};
</script>

<template>
    <Head title="Ouvrir une journée" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Ouvrir une journée</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!form.isDirty" @click="handleSubmit"><Save /></Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Ouvrir la journée</p></TooltipContent>
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
                        <Label for="season_id">Saison</Label>
                        <Select v-model="form.season_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner une saison" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="season in seasons" :key="season.id" :value="String(season.id)">
                                    {{ season.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.season_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="date">Date</Label>
                        <Input id="date" v-model="form.date" type="date" :max="new Date().toISOString().split('T')[0]" required />
                        <InputError :message="form.errors.date" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
