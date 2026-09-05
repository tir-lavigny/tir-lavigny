<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import WebLayout from '@/layouts/WebLayout.vue';
import type { MemberRegistration } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { formatDate } from '@/lib/utils';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    registration: MemberRegistration;
    submitted: boolean;
}>();

const page = usePage();
const flash = page.props.flash as Record<string, string | undefined>;

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

const existingData = props.registration.form_data ?? {};

const form = useForm({
    form_uuid: props.registration.form_uuid,
    last_name: existingData.last_name ?? '',
    first_name: existingData.first_name ?? '',
    email: existingData.email ?? '',
    is_swiss_citizen: existingData.is_swiss_citizen ?? true,
    avs: existingData.avs ?? '',
    tel: existingData.tel ?? '',
    street: existingData.street ?? '',
    npa: existingData.npa ?? '',
    city: existingData.city ?? '',
    canton: existingData.canton ?? 'VD',
    country: existingData.country ?? 'Suisse',
});

const isSwiss = ref(form.is_swiss_citizen === true || form.is_swiss_citizen === 1);

const onSwissChange = (value: boolean | 'indeterminate') => {
    const boolValue = value === true;
    isSwiss.value = boolValue;
    form.is_swiss_citizen = boolValue;
    if (!boolValue) {
        form.avs = '';
    }
};

const isOtherCountry = computed(() => form.country !== 'Suisse');

const handleCountryChange = (value: string | null) => {
    form.country = value ?? '';
};

const handleCantonChange = (value: string | null) => {
    form.canton = value ?? '';
};

const submit = () => {
    form.post(route('member-registration.store'), {
        // onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Inscription" />
    <WebLayout>
        <div class="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="submitted" class="space-y-6">
                <div class="text-center">
                    <Badge variant="secondary" class="mb-4">Formulaire soumis</Badge>
                    <h1 class="text-2xl font-bold sm:text-3xl">Inscription</h1>
                    <p class="text-muted-foreground mt-2">
                        Votre formulaire a été soumis le {{ formatDate(registration.form_submitted_at!, 'dddd DD MMMM YYYY à HH:mm') }}.
                    </p>
                </div>

                <Separator />

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <Label class="text-muted-foreground text-xs">Prénom</Label>
                            <p class="font-medium">{{ form.first_name }}</p>
                        </div>
                        <div>
                            <Label class="text-muted-foreground text-xs">Nom</Label>
                            <p class="font-medium">{{ form.last_name }}</p>
                        </div>
                    </div>

                    <div>
                        <Label class="text-muted-foreground text-xs">Email</Label>
                        <p class="font-medium">{{ form.email }}</p>
                    </div>

                    <div>
                        <Label class="text-muted-foreground text-xs">Citoyen suisse</Label>
                        <p class="font-medium">{{ isSwiss ? 'Oui' : 'Non' }}</p>
                    </div>

                    <div v-if="isSwiss || form.avs">
                        <Label class="text-muted-foreground text-xs">N° AVS</Label>
                        <p class="font-mono font-medium">{{ form.avs }}</p>
                    </div>

                    <div v-if="form.tel">
                        <Label class="text-muted-foreground text-xs">Téléphone</Label>
                        <p class="font-medium">{{ form.tel }}</p>
                    </div>

                    <Separator />

                    <div>
                        <Label class="text-muted-foreground text-xs">Adresse</Label>
                        <p class="font-medium">{{ form.street }}</p>
                        <p class="font-medium">{{ form.npa }} {{ form.city }}</p>
                        <p class="font-medium">{{ form.canton }}, {{ form.country }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="space-y-6">
                <div class="text-center">
                    <h1 class="text-2xl font-bold sm:text-3xl">Formulaire d'inscription</h1>
                    <p class="text-muted-foreground mt-2">Veuillez remplir vos coordonnées pour finaliser votre inscription.</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div v-if="flash?.success" class="rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/50 dark:text-green-300">
                        {{ flash.success }}
                    </div>

                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold">Informations personnelles</h2>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="first_name">Prénom *</Label>
                                <Input id="first_name" v-model="form.first_name" type="text" required placeholder="Jean" :disabled="submitted" />
                                <InputError :message="form.errors.first_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="last_name">Nom *</Label>
                                <Input id="last_name" v-model="form.last_name" type="text" required placeholder="Dupont" :disabled="submitted" />
                                <InputError :message="form.errors.last_name" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="jean.dupont@example.com"
                                :disabled="submitted"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <Checkbox
                                    id="is_swiss_citizen"
                                    :default-value="form.is_swiss_citizen"
                                    :checked="isSwiss"
                                    @update:model-value="onSwissChange"
                                    :disabled="submitted"
                                />
                                <Label for="is_swiss_citizen" class="cursor-pointer">Citoyen suisse</Label>
                            </div>
                            <InputError :message="form.errors.is_swiss_citizen" />
                        </div>

                        <div v-if="isSwiss" class="space-y-2">
                            <Label for="avs">N° AVS *</Label>
                            <Input id="avs" v-model="form.avs" type="text" placeholder="756.XXXX.XXXX.XX" :disabled="submitted" />
                            <InputError :message="form.errors.avs" />
                        </div>

                        <div class="space-y-2">
                            <Label for="tel">Téléphone</Label>
                            <Input id="tel" v-model="form.tel" type="tel" placeholder="+41 XX XXX XX XX" :disabled="submitted" />
                            <InputError :message="form.errors.tel" />
                        </div>
                    </div>

                    <Separator />

                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold">Adresse</h2>

                        <div class="space-y-2">
                            <Label for="street">Rue *</Label>
                            <Input id="street" v-model="form.street" type="text" required placeholder="Rue du Marché 1" :disabled="submitted" />
                            <InputError :message="form.errors.street" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="npa">NPA *</Label>
                                <Input id="npa" v-model="form.npa" type="number" required placeholder="1003" :disabled="submitted" />
                                <InputError :message="form.errors.npa" />
                            </div>

                            <div class="space-y-2">
                                <Label for="city">Ville *</Label>
                                <Input id="city" v-model="form.city" type="text" required placeholder="Lausanne" :disabled="submitted" />
                                <InputError :message="form.errors.city" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Canton *</Label>
                            <Select :model-value="form.canton" @update:model-value="handleCantonChange" :disabled="submitted">
                                <SelectTrigger>
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
                            <Select :model-value="form.country" @update:model-value="handleCountryChange" :disabled="submitted">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un pays" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Suisse">Suisse</SelectItem>
                                    <SelectItem value="Autre">Autre</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.country" />
                        </div>

                        <div v-if="isOtherCountry" class="space-y-2">
                            <Label for="country_other">Pays (libre) *</Label>
                            <Input id="country_other" v-model="form.country" type="text" required placeholder="Nom du pays" :disabled="submitted" />
                            <InputError :message="form.errors.country" />
                        </div>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Envoyer
                    </Button>
                </form>
            </div>
        </div>
    </WebLayout>
</template>
