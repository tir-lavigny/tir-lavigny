<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

defineProps<{
    methods: Array<{ value: string; label: string }>;
    errors: any;
    amountDisabled?: boolean;
    amountHint?: string | null;
}>();

const amount = defineModel<string>('amount', { default: '' });
const paymentDate = defineModel<string>('paymentDate', { default: '' });
const method = defineModel<string>('method', { default: '' });
const reference = defineModel<string>('reference', { default: '' });
const notes = defineModel<string>('notes', { default: '' });
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="grid gap-2">
            <Label for="amount">Montant</Label>
            <Input id="amount" v-model="amount" type="number" min="0.01" step="0.01" :disabled="amountDisabled" />
            <p v-if="amountHint" class="text-muted-foreground text-xs">{{ amountHint }}</p>
            <InputError :message="errors.amount" />
        </div>

        <div class="grid gap-2">
            <Label for="payment_date">Date du paiement</Label>
            <Input id="payment_date" v-model="paymentDate" type="date" />
            <InputError :message="errors.payment_date" />
        </div>

        <div class="grid gap-2">
            <Label for="method">Moyen de paiement</Label>
            <Select v-model="method">
                <SelectTrigger id="method" class="w-full">
                    <SelectValue placeholder="Sélectionner un moyen" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="item in methods" :key="item.value" :value="item.value">{{ item.label }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.method" />
        </div>

        <div class="grid gap-2">
            <Label for="reference">Référence</Label>
            <Input id="reference" v-model="reference" placeholder="Référence bancaire, reçu, etc." />
            <InputError :message="errors.reference" />
        </div>

        <div class="grid gap-2 md:col-span-2">
            <Label for="notes">Notes</Label>
            <Textarea id="notes" v-model="notes" rows="3" placeholder="Informations complémentaires" />
            <InputError :message="errors.notes" />
        </div>
    </div>
</template>
