<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';

interface MembershipFeeCampaignFormDefaults {
    year: string;
    name: string;
    issue_date: string;
    due_date: string;
}

const props = defineProps<{
    formDefaults: MembershipFeeCampaignFormDefaults;
    submitLabel: string;
    onSubmit: (form: any) => void;
}>();

const form = useForm<MembershipFeeCampaignFormDefaults>({ ...props.formDefaults });

const handleSubmit = () => {
    props.onSubmit(form);
};
</script>

<template>
    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="handleSubmit">
        <div class="grid gap-2">
            <Label for="year">Année</Label>
            <Input id="year" v-model="form.year" inputmode="numeric" placeholder="2027" />
            <p v-if="form.errors.year" class="text-destructive text-sm">{{ form.errors.year }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="name">Nom</Label>
            <Input id="name" v-model="form.name" placeholder="Cotisations 2027" />
            <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="issue_date">Date d’émission</Label>
            <Input id="issue_date" v-model="form.issue_date" type="date" />
            <p v-if="form.errors.issue_date" class="text-destructive text-sm">{{ form.errors.issue_date }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="due_date">Échéance</Label>
            <Input id="due_date" v-model="form.due_date" type="date" />
            <p v-if="form.errors.due_date" class="text-destructive text-sm">{{ form.errors.due_date }}</p>
        </div>

        <div class="md:col-span-2">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
