<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { MemberRegistration } from '@/types';
import { formatDate } from '@/lib/utils';

const props = defineProps<{
    registration: MemberRegistration;
}>();

const data = props.registration.form_data ?? {};
const isSwiss = data.is_swiss_citizen === true || data.is_swiss_citizen === 1;
</script>

<template>
    <div class="space-y-6">
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
                    <p class="font-medium">{{ data.first_name }}</p>
                </div>
                <div>
                    <Label class="text-muted-foreground text-xs">Nom</Label>
                    <p class="font-medium">{{ data.last_name }}</p>
                </div>
            </div>

            <div>
                <Label class="text-muted-foreground text-xs">Email</Label>
                <p class="font-medium">{{ data.email }}</p>
            </div>

            <div>
                <Label class="text-muted-foreground text-xs">Citoyen suisse</Label>
                <p class="font-medium">{{ isSwiss ? 'Oui' : 'Non' }}</p>
            </div>

            <div v-if="isSwiss || data.avs">
                <Label class="text-muted-foreground text-xs">N° AVS</Label>
                <p class="font-mono font-medium">{{ data.avs }}</p>
            </div>

            <div v-if="data.tel">
                <Label class="text-muted-foreground text-xs">Téléphone</Label>
                <p class="font-medium">{{ data.tel }}</p>
            </div>

            <Separator />

            <div>
                <Label class="text-muted-foreground text-xs">Adresse</Label>
                <p class="font-medium">{{ data.street }}</p>
                <p class="font-medium">{{ data.npa }} {{ data.city }}</p>
                <p class="font-medium">{{ data.canton }}, {{ data.country }}</p>
            </div>
        </div>
    </div>
</template>