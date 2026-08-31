<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Saisons', href: route('bo.register.seasons.index') },
    { title: 'Créer', href: route('bo.register.seasons.create') },
];

const form = useForm({ name: '', start_date: '', end_date: '', initial_balance: 0 });

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'manage seasons')) {
        toast.error("Vous n'avez pas les droits.");
        return;
    }
    form.post(route('bo.register.seasons.store'), {
        onSuccess: () => {
            form.reset();
            toast.success('Saison créée.');
        },
        onError: () => toast.error('Erreur lors de la création.'),
    });
};
</script>

<template>
    <Head title="Créer une saison" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Créer une saison</H1>
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
                        <Input id="name" v-model="form.name" type="text" placeholder="Ex: Saison 2026" maxlength="255" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="start_date">Date de début</Label>
                            <Input id="start_date" v-model="form.start_date" type="date" required />
                            <InputError :message="form.errors.start_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="end_date">Date de fin</Label>
                            <Input id="end_date" v-model="form.end_date" type="date" required />
                            <InputError :message="form.errors.end_date" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="initial_balance">Solde initial (CHF)</Label>
                        <Input id="initial_balance" v-model="form.initial_balance" type="number" step="0.01" min="0" required />
                        <InputError :message="form.errors.initial_balance" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
