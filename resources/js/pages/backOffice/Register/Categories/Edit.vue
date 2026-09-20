<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
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

const props = defineProps<{ category: Category }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catégories', href: route('bo.register.categories.index') },
    { title: props.category.name, href: route('bo.register.categories.edit', props.category.id) },
];

const form = useForm({ name: props.category.name, is_restricted: props.category.is_restricted });

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'manage categories')) {
        toast.error("Vous n'avez pas les droits.");
        return;
    }
    form.put(route('bo.register.categories.update', props.category.id), {
        onSuccess: () => toast.success('Catégorie mise à jour.'),
        onError: () => toast.error('Erreur lors de la mise à jour.'),
    });
};
</script>

<template>
    <Head :title="'Modifier ' + category.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Modifier {{ category.name }}</H1>
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
                        <Input id="name" v-model="form.name" type="text" placeholder="Nom de la catégorie" maxlength="255" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch id="is_restricted" v-model="form.is_restricted" />
                        <Label for="is_restricted">Restreinte (admin uniquement)</Label>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
