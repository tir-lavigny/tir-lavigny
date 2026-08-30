<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Separator } from '@/components/ui/separator';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { formatMoney, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Category, Product, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{ product: Product; categories: Category[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produits', href: route('bo.register.products.index') },
    { title: props.product.name, href: route('bo.register.products.show', props.product.id) },
];

const attachForm = useForm({ child_product_id: '', quantity: 1 });
const showAttach = ref(false);

const handleAttach = () => {
    attachForm.post(route('bo.register.products.attach-child', props.product.id), {
        onSuccess: () => {
            attachForm.reset();
            showAttach.value = false;
            toast.success('Sous-produit ajouté.');
        },
        onError: () => toast.error("Erreur lors de l'ajout."),
    });
};

const handleDetach = (childId: number) => {
    useForm({}).delete(route('bo.register.products.detach-child', { product: props.product.id, child: childId }), {
        onSuccess: () => toast.success('Sous-produit retiré.'),
        onError: () => toast.error('Erreur lors du retrait.'),
    });
};
</script>

<template>
    <Head :title="product.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>{{ product.name }}</H1>
            <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                <TooltipTrigger>
                    <Link :href="route('bo.register.products.edit', product.id)">
                        <Button size="icon" variant="outline"><Pencil /></Button>
                    </Link>
                </TooltipTrigger>
                <TooltipContent><p>Modifier</p></TooltipContent>
            </Tooltip>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Détails</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Prix unitaire</dt>
                            <dd class="font-medium">{{ formatMoney(product.unit_price) }} CHF</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Catégorie</dt>
                            <dd class="font-medium">{{ product.category?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Prix libre</dt>
                            <dd>
                                <Badge :variant="product.allows_custom_price ? 'default' : 'secondary'">{{
                                    product.allows_custom_price ? 'Oui' : 'Non'
                                }}</Badge>
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Separator />

            <div class="flex items-center justify-between">
                <H3>Sous-produits</H3>
                <Tooltip v-if="hasPermissionTo(user, 'manage products')">
                    <TooltipTrigger>
                        <Button size="sm" @click="showAttach = !showAttach"><Plus class="mr-1 size-4" /> Ajouter</Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Ajouter un sous-produit</p></TooltipContent>
                </Tooltip>
            </div>

            <div v-if="showAttach" class="flex items-end gap-4 rounded-lg border p-4">
                <div class="grid flex-1 gap-2">
                    <Label for="child_product_id">Produit</Label>
                    <Select v-model="attachForm.child_product_id">
                        <SelectTrigger><SelectValue placeholder="Sélectionner un produit" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="p in categories" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="attachForm.errors.child_product_id" />
                </div>
                <div class="grid w-24 gap-2">
                    <Label for="quantity">Quantité</Label>
                    <Input id="quantity" v-model="attachForm.quantity" type="number" min="1" />
                </div>
                <Button @click="handleAttach" :disabled="attachForm.processing">Ajouter</Button>
            </div>

            <div v-if="product.childProducts?.length" class="flex flex-col gap-2">
                <div v-for="child in product.childProducts" :key="child.id" class="flex items-center justify-between rounded-lg border p-4">
                    <div>
                        <p class="font-medium">{{ child.name }}</p>
                        <p class="text-muted-foreground text-sm">Quantité: {{ child.pivot?.quantity }}</p>
                    </div>
                    <AppAlertDialog
                        v-if="hasPermissionTo(user, 'manage products')"
                        title="Retirer ce sous-produit ?"
                        description="Le sous-produit sera détaché."
                        cancelText="Annuler"
                    >
                        <template #trigger>
                            <Button size="icon" variant="destructive"><Trash2 /></Button>
                        </template>
                        <template #action>
                            <Button variant="destructive" @click="handleDetach(child.id)">Retirer</Button>
                        </template>
                    </AppAlertDialog>
                </div>
            </div>
            <p v-else class="text-muted-foreground text-sm">Aucun sous-produit.</p>
        </div>
    </AppLayout>
</template>
