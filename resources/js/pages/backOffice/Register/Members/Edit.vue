<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Member, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{ member: Member }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Membres', href: route('bo.register.members.index') },
    { title: props.member.first_name + ' ' + props.member.last_name, href: route('bo.register.members.edit', props.member.id) },
];

const form = useForm({
    first_name: props.member.first_name,
    last_name: props.member.last_name,
    member_number: props.member.member_number,
});

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'manage members')) {
        toast.error("Vous n'avez pas les droits.");
        return;
    }
    form.put(route('bo.register.members.update', props.member.id), {
        onSuccess: () => toast.success('Membre mis à jour.'),
        onError: () => toast.error('Erreur lors de la mise à jour.'),
    });
};
</script>

<template>
    <Head :title="'Modifier ' + member.first_name + ' ' + member.last_name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Modifier {{ member.first_name }} {{ member.last_name }}</H1>
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
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="first_name">Prénom</Label>
                            <Input id="first_name" v-model="form.first_name" type="text" placeholder="Prénom" maxlength="255" required />
                            <InputError :message="form.errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_name">Nom</Label>
                            <Input id="last_name" v-model="form.last_name" type="text" placeholder="Nom" maxlength="255" required />
                            <InputError :message="form.errors.last_name" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="member_number">Numéro de membre</Label>
                        <Input id="member_number" v-model="form.member_number" type="text" placeholder="Ex: 1234" maxlength="255" required />
                        <InputError :message="form.errors.member_number" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
