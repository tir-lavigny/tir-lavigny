<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/utils';
import type { BreadcrumbItem, CreditNote } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const props = defineProps<{ creditNote: CreditNote; availableCents: number }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avoirs', href: route('bo.invoicing.credit-notes.index') },
    {
        title: props.creditNote.credit_note_number ?? `Brouillon #${props.creditNote.id}`,
        href: route('bo.invoicing.credit-notes.show', props.creditNote.id),
    },
    { title: "Avoir d'annulation", href: route('bo.invoicing.credit-notes.reversals.create', props.creditNote.id) },
];

const localDate = (date: Date): string => {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

const form = useForm({
    reason: '',
    issue_date: localDate(new Date()),
    items: [
        {
            credit_note_item_id: null as number | null,
            description: "Annulation de l'avoir",
            amount: (props.availableCents / 100).toFixed(2),
        },
    ],
});

const submit = () => {
    form.post(route('bo.invoicing.credit-notes.reversals.store', props.creditNote.id), {
        onSuccess: () => toast.success("Avoir d'annulation brouillon créé."),
        onError: (errors) => toast.error(errors.items ?? errors.action ?? "Erreur lors de la création de l'avoir d'annulation."),
    });
};
</script>

<template>
    <Head title="Avoir d'annulation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4">
            <H1>Créer un avoir d'annulation</H1>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mb-4 rounded-xl border p-4">
            <H3>Avoir d'origine</H3>
            <p class="mt-2 text-sm">
                {{ creditNote.credit_note_number }} · {{ creditNote.recipient_name }} · Disponible {{ formatMoney(availableCents / 100) }}
            </p>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 rounded-xl border p-4">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="reason">Motif</Label>
                    <Input id="reason" v-model="form.reason" type="text" required />
                    <InputError :message="form.errors.reason" />
                </div>
                <div class="grid gap-2">
                    <Label for="issue_date">Date d'émission</Label>
                    <Input id="issue_date" v-model="form.issue_date" type="date" />
                    <InputError :message="form.errors.issue_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="amount">Montant à annuler</Label>
                    <Input id="amount" v-model="form.items[0].amount" type="number" step="0.01" min="0.01" :max="availableCents / 100" required />
                    <p class="text-muted-foreground text-xs">Disponible : {{ formatMoney(availableCents / 100) }}</p>
                    <InputError :message="form.errors['items.0.amount'] ?? form.errors.items" />
                </div>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">Enregistrer le brouillon</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
