<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    formDefaults: {
        type: string;
        first_name: string;
        last_name: string;
        organization_name: string;
        address: string;
        postal_code: string;
        city: string;
        country: string;
        email: string;
        phone: string;
        preferred_invoice_delivery: string;
    };
    types: Array<{ value: string; label: string }>;
    deliveryMethods: Array<{ value: string; label: string }>;
    submitLabel: string;
    onSubmit: (form: any) => void;
}>();

const form = useForm({ ...props.formDefaults });

watch(
    () => props.formDefaults,
    (defaults) => {
        form.defaults(defaults);
        form.reset();
    },
    { deep: true },
);

const handleSubmit = () => {
    props.onSubmit(form);
};

defineExpose({ form });
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="type">Type de client</Label>
                <Select v-model="form.type">
                    <SelectTrigger id="type" class="w-full">
                        <SelectValue placeholder="Sélectionner un type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.type" />
            </div>
            <div class="grid gap-2">
                <Label for="preferred_invoice_delivery">Mode d'envoi préféré</Label>
                <Select v-model="form.preferred_invoice_delivery">
                    <SelectTrigger id="preferred_invoice_delivery" class="w-full">
                        <SelectValue placeholder="Sélectionner un mode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="method in deliveryMethods" :key="method.value" :value="method.value">{{ method.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.preferred_invoice_delivery" />
            </div>

            <template v-if="form.type === 'organization'">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="organization_name">Nom de l'organisation</Label>
                    <Input
                        id="organization_name"
                        v-model="form.organization_name"
                        type="text"
                        placeholder="Nom de l'organisation"
                        maxlength="255"
                        required
                    />
                    <InputError :message="form.errors.organization_name" />
                </div>
            </template>
            <template v-else>
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
            </template>

            <div class="grid gap-2 md:col-span-2">
                <Label for="address">Adresse</Label>
                <Input id="address" v-model="form.address" type="text" placeholder="Rue et numéro" maxlength="255" required />
                <InputError :message="form.errors.address" />
            </div>
            <div class="grid gap-2">
                <Label for="postal_code">Code postal</Label>
                <Input id="postal_code" v-model="form.postal_code" type="text" placeholder="1000" maxlength="20" required />
                <InputError :message="form.errors.postal_code" />
            </div>
            <div class="grid gap-2">
                <Label for="city">Ville</Label>
                <Input id="city" v-model="form.city" type="text" placeholder="Ville" maxlength="255" required />
                <InputError :message="form.errors.city" />
            </div>
            <div class="grid gap-2">
                <Label for="country">Pays</Label>
                <Input id="country" v-model="form.country" type="text" placeholder="CH" maxlength="2" required />
                <InputError :message="form.errors.country" />
            </div>
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="Email"
                    maxlength="255"
                    :required="form.preferred_invoice_delivery === 'email'"
                />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Téléphone</Label>
                <Input id="phone" v-model="form.phone" type="tel" placeholder="Téléphone" maxlength="50" />
                <InputError :message="form.errors.phone" />
            </div>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
