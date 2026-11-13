<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Member } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{ member: Member; statuses: Record<string, string> }>();

const swissCantons = [
    { value: 'AG', label: 'Argovie (AG)' },
    { value: 'BL', label: 'Bâle-Campagne (BL)' },
    { value: 'BS', label: 'Bâle-Ville (BS)' },
    { value: 'BE', label: 'Berne (BE)' },
    { value: 'FR', label: 'Fribourg (FR)' },
    { value: 'GE', label: 'Genève (GE)' },
    { value: 'GL', label: 'Glaris (GL)' },
    { value: 'GR', label: 'Grisons (GR)' },
    { value: 'JU', label: 'Jura (JU)' },
    { value: 'LU', label: 'Lucerne (LU)' },
    { value: 'NE', label: 'Neuchâtel (NE)' },
    { value: 'NW', label: 'Nidwald (NW)' },
    { value: 'OW', label: 'Obwald (OW)' },
    { value: 'SG', label: 'Saint-Gall (SG)' },
    { value: 'SH', label: 'Schaffhouse (SH)' },
    { value: 'SO', label: 'Soleure (SO)' },
    { value: 'TI', label: 'Tessin (TI)' },
    { value: 'TG', label: 'Thurgovie (TG)' },
    { value: 'UR', label: 'Uri (UR)' },
    { value: 'VD', label: 'Vaud (VD)' },
    { value: 'VS', label: 'Valais (VS)' },
    { value: 'ZG', label: 'Zoug (ZG)' },
    { value: 'ZH', label: 'Zurich (ZH)' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Membres', href: route('bo.members.index') },
    { title: props.member.first_name + ' ' + props.member.last_name, href: route('bo.members.edit', props.member.id) },
];

const form = useForm({
    first_name: props.member.first_name,
    last_name: props.member.last_name,
    email: props.member.email ?? '',
    tel: props.member.tel ?? '',
    avs: props.member.avs ?? '',
    is_swiss_citizen: props.member.is_swiss_citizen ?? true,
    street: props.member.street ?? '',
    npa: props.member.npa ?? '',
    city: props.member.city ?? '',
    canton: props.member.canton ?? '',
    country: props.member.country ?? 'Suisse',
    status: props.member.status,
});

const cantonFilter = computed({
    get: () => form.canton || 'none',
    set: (value: string) => {
        form.canton = value === 'none' ? '' : value;
    },
});

const handleSubmit = () => {
    form.put(route('bo.members.update', props.member.id), {
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
                <Button size="icon" :disabled="!form.isDirty || form.processing" @click="handleSubmit"><Save /></Button>
                <Button size="icon" variant="destructive" :disabled="!form.isDirty" @click="form.reset()"><Undo2 /></Button>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                <H3>Informations</H3>
                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
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
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" placeholder="Email" maxlength="255" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tel">Téléphone</Label>
                        <Input id="tel" v-model="form.tel" type="tel" placeholder="Téléphone" maxlength="255" />
                        <InputError :message="form.errors.tel" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="avs">AVS</Label>
                        <Input id="avs" v-model="form.avs" type="text" placeholder="756.1234.5678.90" maxlength="255" />
                        <InputError :message="form.errors.avs" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">Statut</Label>
                        <Select v-model="form.status">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un statut" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="street">Rue</Label>
                        <Input id="street" v-model="form.street" type="text" placeholder="Rue" maxlength="255" />
                        <InputError :message="form.errors.street" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="npa">NPA</Label>
                        <Input id="npa" v-model="form.npa" type="text" placeholder="NPA" maxlength="255" />
                        <InputError :message="form.errors.npa" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="city">Ville</Label>
                        <Input id="city" v-model="form.city" type="text" placeholder="Ville" maxlength="255" />
                        <InputError :message="form.errors.city" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="canton">Canton</Label>
                        <Select v-model="cantonFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un canton" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Aucun</SelectItem>
                                <SelectItem v-for="canton in swissCantons" :key="canton.value" :value="canton.value">
                                    {{ canton.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.canton" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="country">Pays</Label>
                        <Input id="country" v-model="form.country" type="text" placeholder="Pays" maxlength="255" />
                        <InputError :message="form.errors.country" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="is_swiss_citizen">Citoyen suisse</Label>
                        <Switch id="is_swiss_citizen" v-model="form.is_swiss_citizen" />
                        <InputError :message="form.errors.is_swiss_citizen" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
