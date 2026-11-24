<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

interface InvoicingSettings {
    creditor_name: string;
    creditor_address: string;
    creditor_postal_code: string;
    creditor_city: string;
    creditor_country: string;
    creditor_iban: string;
}

const props = defineProps<{
    settings: InvoicingSettings | null;
    configured: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Factures', href: route('bo.invoicing.invoices.index') },
    { title: 'Paramètres de facturation', href: route('bo.invoicing.settings.edit') },
];

const form = useForm({
    creditor_name: props.settings?.creditor_name ?? '',
    creditor_address: props.settings?.creditor_address ?? '',
    creditor_postal_code: props.settings?.creditor_postal_code ?? '',
    creditor_city: props.settings?.creditor_city ?? '',
    creditor_country: props.settings?.creditor_country ?? 'CH',
    creditor_iban: props.settings?.creditor_iban ?? '',
});

const submit = () => {
    form.put(route('bo.invoicing.settings.update'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Paramètres de facturation enregistrés.'),
        onError: () => toast.error('Vérifiez les paramètres de facturation.'),
    });
};
</script>

<template>
    <Head title="Paramètres de facturation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Paramètres de facturation</H1>
            <p class="text-muted-foreground mt-2 text-sm">
                Ces informations sont utilisées pour les factures, les QR-bills et les documents archivés.
            </p>
        </div>

        <div v-if="!configured" class="border-destructive/40 bg-destructive/5 mx-4 rounded-xl border p-4 text-sm">
            Les paramètres sont incomplets. La création et l’émission de factures sont bloquées.
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Créancier</H3>
            <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <div class="md:col-span-2">
                    <Label for="creditor_name">Nom</Label>
                    <Input id="creditor_name" v-model="form.creditor_name" class="mt-1" autocomplete="organization" />
                    <p v-if="form.errors.creditor_name" class="text-destructive mt-1 text-sm">{{ form.errors.creditor_name }}</p>
                </div>

                <div class="md:col-span-2">
                    <Label for="creditor_address">Adresse</Label>
                    <Input id="creditor_address" v-model="form.creditor_address" class="mt-1" autocomplete="street-address" />
                    <p v-if="form.errors.creditor_address" class="text-destructive mt-1 text-sm">{{ form.errors.creditor_address }}</p>
                </div>

                <div>
                    <Label for="creditor_postal_code">Code postal</Label>
                    <Input id="creditor_postal_code" v-model="form.creditor_postal_code" class="mt-1" autocomplete="postal-code" />
                    <p v-if="form.errors.creditor_postal_code" class="text-destructive mt-1 text-sm">{{ form.errors.creditor_postal_code }}</p>
                </div>

                <div>
                    <Label for="creditor_city">Localité</Label>
                    <Input id="creditor_city" v-model="form.creditor_city" class="mt-1" autocomplete="address-level2" />
                    <p v-if="form.errors.creditor_city" class="text-destructive mt-1 text-sm">{{ form.errors.creditor_city }}</p>
                </div>

                <div>
                    <Label for="creditor_country">Pays</Label>
                    <Input id="creditor_country" v-model="form.creditor_country" class="mt-1" autocomplete="country" />
                    <p v-if="form.errors.creditor_country" class="text-destructive mt-1 text-sm">{{ form.errors.creditor_country }}</p>
                </div>

                <div>
                    <Label for="creditor_iban">IBAN</Label>
                    <Input
                        id="creditor_iban"
                        v-model="form.creditor_iban"
                        class="mt-1 font-mono"
                        autocomplete="off"
                        placeholder="CH93 0076 2011 6238 5295 7"
                    />
                    <p v-if="form.errors.creditor_iban" class="text-destructive mt-1 text-sm">{{ form.errors.creditor_iban }}</p>
                </div>

                <div class="md:col-span-2">
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
