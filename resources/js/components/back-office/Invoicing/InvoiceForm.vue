<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';

export interface InvoiceLineForm {
    type: string;
    description: string;
    quantity: string;
    unit_price: string;
    product_id: number | null;
    metadata: any;
}

interface InvoiceFormDefaults {
    billing_customer_id: number | string;
    delivery_method: string;
    issue_date: string;
    due_date: string;
    items: InvoiceLineForm[];
}

interface InvoiceTotalsPreview {
    lines: Array<{ total_cents: number; total: string }>;
    subtotal_cents: number;
    subtotal: string;
    discounts_cents: number;
    discounts: string;
    total_cents: number;
    total: string;
}

const props = defineProps<{
    formDefaults: InvoiceFormDefaults;
    billingCustomers: Array<{ id: number; name: string; email: string | null }>;
    deliveryMethods: Array<{ value: string; label: string }>;
    previewUrl: string;
    submitLabel: string;
    onSubmit: (form: any) => void;
}>();

const blankLine = (): InvoiceLineForm => ({
    type: 'fee',
    description: '',
    quantity: '1',
    unit_price: '',
    product_id: null,
    metadata: null,
});

const initialItems = props.formDefaults.items.length > 0 ? props.formDefaults.items : [blankLine()];

const form = useForm<any>({
    ...props.formDefaults,
    items: initialItems,
});

const totals = ref<InvoiceTotalsPreview | null>(null);
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
        totals.value = (await response.json()) as InvoiceTotalsPreview;
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

const addLine = (type = 'fee') => {
    form.items.push({ ...blankLine(), type });
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
            <div class="grid gap-2 md:col-span-2">
                <Label for="billing_customer_id">Client de facturation</Label>
                <Select v-model="form.billing_customer_id">
                    <SelectTrigger id="billing_customer_id" class="w-full">
                        <SelectValue placeholder="Sélectionner un client" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="customer in billingCustomers" :key="customer.id" :value="String(customer.id)">
                            {{ customer.name }}
                            <span v-if="customer.email">· {{ customer.email }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.billing_customer_id" />
            </div>

            <div class="grid gap-2">
                <Label for="delivery_method">Mode d'envoi</Label>
                <Select v-model="form.delivery_method">
                    <SelectTrigger id="delivery_method" class="w-full">
                        <SelectValue placeholder="Sélectionner un mode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="method in deliveryMethods" :key="method.value" :value="method.value">{{ method.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.delivery_method" />
            </div>

            <div class="grid gap-2">
                <Label for="issue_date">Date d'émission</Label>
                <Input id="issue_date" v-model="form.issue_date" type="date" />
                <InputError :message="form.errors.issue_date" />
            </div>

            <div class="grid gap-2">
                <Label for="due_date">Échéance</Label>
                <Input id="due_date" v-model="form.due_date" type="date" required />
                <InputError :message="form.errors.due_date" />
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <Label>Lignes de facture</Label>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="addLine('fee')"> <Plus class="mr-2 h-4 w-4" /> Ligne </Button>
                    <Button type="button" variant="outline" @click="addLine('discount')"> <Plus class="mr-2 h-4 w-4" /> Remise </Button>
                </div>
            </div>

            <p v-if="form.errors.items" class="text-destructive text-sm">{{ form.errors.items }}</p>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="w-36 px-3 py-2 text-left font-medium">Type</th>
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
                                <Select v-model="item.type">
                                    <SelectTrigger class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="product">Produit</SelectItem>
                                        <SelectItem value="fee">Taxe</SelectItem>
                                        <SelectItem value="discount">Remise</SelectItem>
                                        <SelectItem value="adjustment">Ajustement</SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="item.description" type="text" placeholder="Description" maxlength="500" required />
                                <InputError :message="form.errors[`items.${index}.description`]" />
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="item.quantity" type="number" step="0.001" min="0.001" required />
                                <InputError :message="form.errors[`items.${index}.quantity`]" />
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="item.unit_price" type="number" step="0.01" required />
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
            <div>
                Sous-total: <span class="font-mono">{{ totals?.subtotal ?? '0.00' }}</span>
            </div>
            <div>
                Remises: <span class="font-mono">{{ totals?.discounts ?? '0.00' }}</span>
            </div>
            <div class="text-base font-semibold">
                Total: <span class="font-mono">{{ totals?.total ?? '0.00' }}</span>
            </div>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
