<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';

interface CreditNoteLineForm {
    invoice_item_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
}

interface CreditNoteTotalsPreview {
    lines: Array<{ total_cents: number; total: string }>;
    subtotal_cents: number;
    subtotal: string;
    total_cents: number;
    total: string;
}

const props = defineProps<{
    formDefaults: {
        invoice_id: number;
        reason: string;
        issue_date: string;
        items: CreditNoteLineForm[];
    };
    previewUrl: string;
    submitLabel: string;
    remainingCreditableCents: number;
    onSubmit: (form: any) => void;
}>();

const blankLine = (): CreditNoteLineForm => ({
    invoice_item_id: null,
    description: '',
    quantity: '1',
    unit_price: '',
});

const form = useForm<any>({
    ...props.formDefaults,
    items: props.formDefaults.items.length > 0 ? props.formDefaults.items : [blankLine()],
});

const totals = ref<CreditNoteTotalsPreview | null>(null);
let previewTimer: ReturnType<typeof setTimeout> | undefined;

const fetchPreview = async () => {
    if (form.items.length === 0) {
        totals.value = null;
        return;
    }

    const xsrfToken = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
    const response = await fetch(props.previewUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        body: JSON.stringify({ items: form.items }),
    });

    if (response.ok) {
        totals.value = (await response.json()) as CreditNoteTotalsPreview;
    }
};

const schedulePreview = () => {
    if (previewTimer) {
        clearTimeout(previewTimer);
    }

    previewTimer = setTimeout(fetchPreview, 300);
};

watch(
    () => form.items,
    () => schedulePreview(),
    { deep: true },
);

watch(
    () => props.formDefaults,
    (defaults) => {
        form.defaults(defaults);
        form.reset();
        schedulePreview();
    },
    { deep: true },
);

onMounted(fetchPreview);

const addLine = () => {
    form.items.push(blankLine());
};

const removeLine = (index: number | string) => {
    form.items.splice(Number(index), 1);
};

const handleSubmit = () => {
    props.onSubmit(form);
};

const lineTotal = (index: number | string): string => totals.value?.lines[Number(index)]?.total ?? '0.00';
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="reason">Motif</Label>
                <Input id="reason" v-model="form.reason" type="text" placeholder="Motif de l'avoir" maxlength="1000" required />
                <InputError :message="form.errors.reason" />
            </div>
            <div class="grid gap-2">
                <Label for="issue_date">Date d'émission</Label>
                <Input id="issue_date" v-model="form.issue_date" type="date" />
                <InputError :message="form.errors.issue_date" />
            </div>
        </div>

        <p class="text-muted-foreground text-sm">
            Montant encore créditable : <span class="font-mono">{{ (remainingCreditableCents / 100).toFixed(2) }}</span> CHF
        </p>
        <p v-if="form.errors.items" class="text-destructive text-sm">{{ form.errors.items }}</p>

        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <Label>Lignes de l'avoir</Label>
                <Button type="button" variant="outline" @click="addLine"> <Plus class="mr-2 h-4 w-4" /> Ligne </Button>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Description</th>
                            <th class="w-28 px-3 py-2 text-left font-medium">Quantité</th>
                            <th class="w-36 px-3 py-2 text-left font-medium">Prix unitaire</th>
                            <th class="w-28 px-3 py-2 text-right font-medium">Total</th>
                            <th class="w-12 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in form.items" :key="index" class="border-sidebar-border/70 dark:border-sidebar-border border-t">
                            <td class="px-3 py-2">
                                <Input v-model="item.description" type="text" placeholder="Description" maxlength="500" required />
                                <InputError :message="form.errors[`items.${index}.description`]" />
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="item.quantity" type="number" step="0.001" min="0.001" required />
                                <InputError :message="form.errors[`items.${index}.quantity`]" />
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="item.unit_price" type="number" step="0.01" min="0.01" required />
                                <InputError :message="form.errors[`items.${index}.unit_price`]" />
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ lineTotal(index) }}</td>
                            <td class="px-3 py-2">
                                <Button type="button" size="icon" variant="ghost" @click="removeLine(index)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col items-end gap-1 text-sm">
            <div class="text-base font-semibold">
                Total: <span class="font-mono">{{ totals?.total ?? '0.00' }}</span>
            </div>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
