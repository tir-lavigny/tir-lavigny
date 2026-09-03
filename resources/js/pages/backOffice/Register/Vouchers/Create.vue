<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Member, Product, Season, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

defineProps<{
    products: Product[];
    seasons: Season[];
    members: Member[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bons', href: route('bo.register.vouchers.index') },
    { title: 'Créer', href: route('bo.register.vouchers.create') },
];

const discountType = ref<'percent' | 'fixed'>('percent');

const form = useForm({
    product_id: '',
    season_id: '',
    member_id: '',
    discount_percent: 10,
    discount_fixed: 0,
});

watch(discountType, (type) => {
    if (type === 'percent') {
        form.discount_fixed = 0;
    } else {
        form.discount_percent = 0;
    }
});

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'manage vouchers')) {
        toast.error("Vous n'avez pas les droits.");
        return;
    }
    form.post(route('bo.register.vouchers.store'), {
        onSuccess: () => {
            form.reset();
            toast.success('Bon créé.');
        },
        onError: () => toast.error('Erreur lors de la création.'),
    });
};
</script>

<template>
    <Head title="Créer un bon" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Créer un bon</H1>
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
                    <div class="grid grid-cols-3 gap-4">
                        <div class="grid gap-2">
                            <Label for="product_id">Produit</Label>
                            <Select v-model="form.product_id">
                                <SelectTrigger><SelectValue placeholder="Sélectionner" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="p in products" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.product_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="season_id">Saison</Label>
                            <Select v-model="form.season_id">
                                <SelectTrigger><SelectValue placeholder="Sélectionner" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in seasons" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.season_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="member_id">Membre</Label>
                            <Select v-model="form.member_id">
                                <SelectTrigger><SelectValue placeholder="Sélectionner" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="m in members" :key="m.id" :value="String(m.id)"
                                        >{{ m.first_name }} {{ m.last_name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.member_id" />
                        </div>
                    </div>
                    <Separator />
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Type de réduction</Label>
                            <div class="flex gap-2">
                                <Button :variant="discountType === 'percent' ? 'default' : 'outline'" @click="discountType = 'percent'" type="button"
                                    >Pourcentage (%)</Button
                                >
                                <Button :variant="discountType === 'fixed' ? 'default' : 'outline'" @click="discountType = 'fixed'" type="button"
                                    >Montant fixe (CHF)</Button
                                >
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="discount_value">Valeur</Label>
                            <Input
                                v-if="discountType === 'percent'"
                                id="discount_value"
                                v-model="form.discount_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                            />
                            <Input v-else id="discount_value" v-model="form.discount_fixed" type="number" min="0" step="0.01" required />
                            <InputError :message="form.errors.discount_percent ?? form.errors.discount_fixed" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
