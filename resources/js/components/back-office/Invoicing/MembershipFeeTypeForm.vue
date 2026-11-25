<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';

interface MembershipFeeTypeFormDefaults {
    name: string;
    member_status: string;
    description: string;
    amount: string;
    is_active: boolean;
}

const props = defineProps<{
    formDefaults: MembershipFeeTypeFormDefaults;
    memberStatuses: Array<{ value: string; label: string }>;
    submitLabel: string;
    onSubmit: (form: any) => void;
}>();

const form = useForm<MembershipFeeTypeFormDefaults>({ ...props.formDefaults });

const handleSubmit = () => {
    props.onSubmit(form);
};
</script>

<template>
    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="handleSubmit">
        <div class="grid gap-2">
            <Label for="name">Nom</Label>
            <Input id="name" v-model="form.name" placeholder="Cotisation membre actif" />
            <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="member_status">Statut de membre</Label>
            <Select v-model="form.member_status">
                <SelectTrigger id="member_status" class="w-full">
                    <SelectValue placeholder="Sélectionner un statut" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="status in memberStatuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.member_status" class="text-destructive text-sm">{{ form.errors.member_status }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="amount">Montant (CHF)</Label>
            <Input id="amount" v-model="form.amount" inputmode="decimal" placeholder="150.00" />
            <p v-if="form.errors.amount" class="text-destructive text-sm">{{ form.errors.amount }}</p>
        </div>

        <div class="flex items-center justify-between gap-4 rounded-lg border p-3">
            <div>
                <Label for="is_active">Type actif</Label>
                <p class="text-muted-foreground text-sm">Un type inactif n’est pas utilisé pour les nouveaux aperçus.</p>
            </div>
            <Switch id="is_active" v-model="form.is_active" />
        </div>

        <div class="grid gap-2 md:col-span-2">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" rows="3" placeholder="Description optionnelle" />
            <p v-if="form.errors.description" class="text-destructive text-sm">{{ form.errors.description }}</p>
        </div>

        <div class="md:col-span-2">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
