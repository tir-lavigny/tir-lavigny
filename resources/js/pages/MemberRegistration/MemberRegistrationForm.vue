<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { MemberRegistration } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref, onMounted, watch } from 'vue';
import { watchDebounced } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { H1 } from '@/components/typography/headings';
import { P } from '@/components/typography/texts';

const props = defineProps<{
    registration: MemberRegistration;
}>();

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

const STORAGE_KEY = `member-registration-form:${props.registration.form_uuid}`;

const form = useForm({
    form_uuid: props.registration.form_uuid,
    last_name: '',
    first_name: '',
    email: '',
    is_swiss_citizen: true,
    avs: '',
    tel: '',
    street: '',
    npa: '',
    city: '',
    canton: 'VD',
    country: 'Suisse',
});

const currentStep = ref(1);
const totalSteps = 2;

const isSwiss = ref(form.is_swiss_citizen === true);

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            Object.assign(form, parsed);
            if (parsed.currentStep) {
                currentStep.value = parsed.currentStep;
            }
            isSwiss.value = form.is_swiss_citizen;
        } catch {
            localStorage.removeItem(STORAGE_KEY);
        }
    }
});

watchDebounced(
    () => JSON.stringify(form.data()),
    () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...form.data(), currentStep: currentStep.value }));
    },
    { debounce: 500 },
);

watch(currentStep, () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...form.data(), currentStep: currentStep.value }));
});

const onSwissChange = (value: boolean | 'indeterminate') => {
    const boolValue = value === true;
    isSwiss.value = boolValue;
    form.is_swiss_citizen = boolValue;
    if (!boolValue) {
        form.avs = '';
    }
};

const handleCountryChange = (value: AcceptableValue) => {
    form.country = String(value ?? '');
};

const handleCantonChange = (value: AcceptableValue) => {
    form.canton = String(value ?? '');
};

const validateCurrentStep = (): boolean => {
    form.clearErrors();
    let valid = true;

    if (currentStep.value === 1) {
        if (!form.first_name) {
            form.setError('first_name', 'Le prénom est requis.');
            valid = false;
        }
        if (!form.last_name) {
            form.setError('last_name', 'Le nom est requis.');
            valid = false;
        }
        if (!form.email) {
            form.setError('email', "L'email est requis.");
            valid = false;
        }
        if (!form.tel) {
            form.setError('tel', 'Le téléphone est requis.');
            valid = false;
        }
        if (isSwiss.value && !form.avs) {
            form.setError('avs', 'Le N° AVS est requis.');
            valid = false;
        }
        if (isSwiss.value && form.avs) {
            const avsPattern = /^\d{3}\.\d{4}\.\d{4}\.\d{2}$/;
            if (!avsPattern.test(form.avs)) {
                form.setError('avs', 'Le N° AVS doit être au format 756.XXXX.XXXX.XX.');
                valid = false;
            }
        }
    }
    if (currentStep.value === 2) {
        if (!form.street) {
            form.setError('street', 'La rue est requise.');
            valid = false;
        }
        if (!form.npa) {
            form.setError('npa', 'Le NPA est requis.');
            valid = false;
        }
        if (!form.city) {
            form.setError('city', 'La ville est requise.');
            valid = false;
        }
        if (!form.canton) {
            form.setError('canton', 'Le canton est requis.');
            valid = false;
        }
        if (!form.country) {
            form.setError('country', 'Le pays est requis.');
            valid = false;
        }
    }

    return valid;
};

const nextStep = () => {
    if (validateCurrentStep()) {
        currentStep.value = Math.min(currentStep.value + 1, totalSteps);
    }
};

const prevStep = () => {
    currentStep.value = Math.max(currentStep.value - 1, 1);
};

const submit = () => {
    form.post(route('member-registration.store'), {
        onSuccess: () => {
            localStorage.removeItem(STORAGE_KEY);
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <div class="text-center">
            <H1 class="text-2xl font-bold sm:text-3xl">Formulaire d'inscription</H1>
            <P class="text-muted-foreground mt-2">Veuillez remplir vos coordonnées pour finaliser votre inscription.</P>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div v-if="currentStep === 1" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="first_name">Prénom *</Label>
                        <Input id="first_name" v-model="form.first_name" type="text" required placeholder="Jean" />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="last_name">Nom *</Label>
                        <Input id="last_name" v-model="form.last_name" type="text" required placeholder="Dupont" />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <Checkbox
                            id="is_swiss_citizen"
                            :default-value="form.is_swiss_citizen"
                            :checked="isSwiss"
                            @update:model-value="onSwissChange"
                        />
                        <Label for="is_swiss_citizen" class="cursor-pointer">Citoyen suisse</Label>
                    </div>
                    <InputError :message="form.errors.is_swiss_citizen" />
                </div>

                <div v-if="isSwiss" class="space-y-2">
                    <Label for="avs">N° AVS *</Label>
                    <Input id="avs" v-model="form.avs" type="text" :required="isSwiss" placeholder="756.XXXX.XXXX.XX" />
                    <InputError :message="form.errors.avs" />
                </div>

                <div class="space-y-2">
                    <Label for="email">Email *</Label>
                    <Input id="email" v-model="form.email" type="email" required placeholder="jean.dupont@example.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="space-y-2">
                    <Label for="tel">Téléphone *</Label>
                    <Input id="tel" v-model="form.tel" type="tel" required placeholder="+41 XX XXX XX XX" />
                    <InputError :message="form.errors.tel" />
                </div>
            </div>

            <div v-if="currentStep === 2" class="space-y-4">
                <div class="space-y-2">
                    <Label for="street">Rue *</Label>
                    <Input id="street" v-model="form.street" type="text" required placeholder="Rue du Marché 1" />
                    <InputError :message="form.errors.street" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="npa">NPA *</Label>
                        <Input id="npa" v-model="form.npa" type="number" required placeholder="1003" />
                        <InputError :message="form.errors.npa" />
                    </div>
                    <div class="space-y-2">
                        <Label for="city">Ville *</Label>
                        <Input id="city" v-model="form.city" type="text" required placeholder="Lausanne" />
                        <InputError :message="form.errors.city" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label>Canton *</Label>
                        <Select :model-value="form.canton" @update:model-value="handleCantonChange">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Sélectionner un canton" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="canton in swissCantons" :key="canton.value" :value="canton.value">
                                    {{ canton.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.canton" />
                    </div>

                    <div class="space-y-2">
                        <Label>Pays *</Label>
                        <Select :model-value="form.country" @update:model-value="handleCountryChange">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Sélectionner un pays" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Suisse">Suisse</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.country" />
                    </div>
                </div>
            </div>

            <div class="flex justify-between">
                <Button type="button" variant="outline" @click="prevStep" :disabled="currentStep === 1">Précédent</Button>
                <Button v-if="currentStep < totalSteps" type="button" @click="nextStep">Suivant</Button>
                <Button v-if="currentStep === totalSteps" type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Envoyer
                </Button>
            </div>
        </form>
    </div>
</template>
