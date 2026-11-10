<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Season, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{ season: Season; closedDaysCount: number }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Saisons', href: route('bo.register.seasons.index') },
    { title: props.season.name, href: route('bo.register.seasons.show', props.season.id) },
    { title: 'Modifier', href: route('bo.register.seasons.edit', props.season.id) },
];

const form = useForm({
    name: props.season.name,
    start_date: props.season.start_date?.slice(0, 10),
    end_date: props.season.end_date?.slice(0, 10),
    initial_balance: props.season.initial_balance,
});

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'manage seasons')) {
        toast.error("Vous n'avez pas les droits.");
        return;
    }

    const initialBalanceChanged = Number(form.initial_balance) !== Number(props.season.initial_balance);
    if (initialBalanceChanged && props.closedDaysCount > 0) {
        showCascadeDialog.value = true;
        return;
    }

    submit();
};

const showCascadeDialog = ref(false);

const submit = () => {
    showCascadeDialog.value = false;
    form.put(route('bo.register.seasons.update', props.season.id), {
        onSuccess: () => toast.success('Saison mise à jour.'),
        onError: () => toast.error('Erreur lors de la mise à jour.'),
    });
};
</script>

<template>
    <Head :title="'Modifier ' + season.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Modifier {{ season.name }}</H1>
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
        <AlertDialog :open="showCascadeDialog" @update:open="showCascadeDialog = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Recalculer les journées fermées ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Le solde initial change. Le solde de clôture des {{ closedDaysCount }} journée(s) déjà fermée(s) de cette saison sera
                        recalculé en cascade.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                    <AlertDialogAction @click="submit">Confirmer</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
