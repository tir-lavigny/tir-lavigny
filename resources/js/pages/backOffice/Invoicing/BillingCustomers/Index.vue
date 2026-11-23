<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H1 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BillingCustomer, BreadcrumbItem, PaginatedBillingCustomers, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, RefreshCw, Search, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    billingCustomers: PaginatedBillingCustomers;
    filters: { search?: string | null; type?: string | null; delivery?: string | null };
    types: Array<{ value: string; label: string }>;
    deliveryMethods: Array<{ value: string; label: string }>;
    members: Array<{ id: number; first_name: string; last_name: string; email: string | null }>;
}>();

const page = usePage<SharedData>();
const canManage = computed(() => hasPermissionTo(page.props.auth.user, 'manage invoicing'));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clients de facturation', href: route('bo.invoicing.billing-customers.index') }];

const { filters } = useListFilters('bo.invoicing.billing-customers.index', {
    search: props.filters.search || '',
    type: props.filters.type || '',
    delivery: props.filters.delivery || '',
});

const typeFilter = computed({
    get: () => filters.value.type || 'all',
    set: (value: string) => {
        filters.value.type = value === 'all' ? '' : value;
    },
});

const deliveryFilter = computed({
    get: () => filters.value.delivery || 'all',
    set: (value: string) => {
        filters.value.delivery = value === 'all' ? '' : value;
    },
});

const syncDialogOpen = ref(false);
const syncForm = useForm({ member_id: '' });

const submitSync = () => {
    syncForm.post(route('bo.invoicing.billing-customers.sync-member'), {
        onSuccess: () => {
            syncDialogOpen.value = false;
            syncForm.reset();
            toast.success('Client de facturation créé depuis le membre.');
        },
        onError: () => toast.error('Erreur lors de la synchronisation.'),
    });
};

const deleteCustomer = (customer: BillingCustomer) => {
    router.delete(route('bo.invoicing.billing-customers.destroy', customer.id), {
        preserveScroll: true,
        onSuccess: () => toast.success('Client de facturation supprimé.'),
        onError: (errors) => toast.error(Object.values(errors).join(', ') || 'Erreur lors de la suppression.'),
    });
};

const displayName = (customer: BillingCustomer): string => {
    if (customer.type === 'organization') {
        return customer.organization_name || '—';
    }

    return `${customer.first_name ?? ''} ${customer.last_name ?? ''}`.trim() || '—';
};

const typeLabel = (value: string): string => props.types.find((type) => type.value === value)?.label ?? value;

const deliveryLabel = (value: string): string => props.deliveryMethods.find((method) => method.value === value)?.label ?? value;

const goToPage = (pageNumber: number) => {
    router.get(route('bo.invoicing.billing-customers.index'), { page: pageNumber, ...filters.value }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Clients de facturation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <H1>Clients de facturation</H1>
            <div v-if="canManage" class="flex flex-row gap-2">
                <Button variant="outline" @click="syncDialogOpen = true"> <RefreshCw class="mr-2 h-4 w-4" /> Depuis un membre </Button>
                <Button as-child>
                    <Link :href="route('bo.invoicing.billing-customers.create')"> <Plus class="mr-2 h-4 w-4" /> Nouveau client </Link>
                </Button>
            </div>
        </div>

        <div class="mx-4 flex flex-col gap-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid flex-1 gap-2">
                    <Label for="search">Rechercher</Label>
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2" />
                        <Input id="search" v-model="filters.search" class="pl-8" placeholder="Nom, organisation ou email" />
                    </div>
                </div>
                <div class="grid gap-2 md:w-56">
                    <Label for="type">Type</Label>
                    <Select v-model="typeFilter">
                        <SelectTrigger id="type">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2 md:w-56">
                    <Label for="delivery">Envoi</Label>
                    <Select v-model="deliveryFilter">
                        <SelectTrigger id="delivery">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="method in deliveryMethods" :key="method.value" :value="method.value">{{ method.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nom</th>
                            <th class="px-4 py-3 text-left font-medium">Type</th>
                            <th class="px-4 py-3 text-left font-medium">Email</th>
                            <th class="px-4 py-3 text-left font-medium">Envoi</th>
                            <th class="px-4 py-3 text-left font-medium">Membre</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="customer in billingCustomers.data"
                            :key="customer.id"
                            class="border-sidebar-border/70 dark:border-sidebar-border border-t"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ displayName(customer) }}</div>
                                <div class="text-muted-foreground">{{ customer.postal_code }} {{ customer.city }}</div>
                            </td>
                            <td class="px-4 py-3">{{ typeLabel(customer.type) }}</td>
                            <td class="px-4 py-3">{{ customer.email || '—' }}</td>
                            <td class="px-4 py-3">{{ deliveryLabel(customer.preferred_invoice_delivery) }}</td>
                            <td class="px-4 py-3">
                                <span v-if="customer.member">{{ customer.member.first_name }} {{ customer.member.last_name }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="canManage" class="flex justify-end gap-2">
                                    <Button as-child size="icon" variant="outline">
                                        <Link :href="route('bo.invoicing.billing-customers.edit', customer.id)">
                                            <Pencil class="h-4 w-4" />
                                            <span class="sr-only">Modifier</span>
                                        </Link>
                                    </Button>
                                    <AppAlertDialog
                                        title="Supprimer ce client ?"
                                        description="Le client sera supprimé définitivement. Les clients liés à des factures ne peuvent pas être supprimés."
                                        cancel-text="Annuler"
                                    >
                                        <template #trigger>
                                            <Button size="icon" variant="destructive">
                                                <Trash2 class="h-4 w-4" />
                                                <span class="sr-only">Supprimer</span>
                                            </Button>
                                        </template>
                                        <template #action>
                                            <Button variant="destructive" @click="deleteCustomer(customer)">Supprimer</Button>
                                        </template>
                                    </AppAlertDialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!billingCustomers.data?.length" class="text-muted-foreground px-4 py-8 text-center">
                    Aucun client de facturation trouvé.
                </div>
            </div>

            <AppPagination
                :items-per-page="billingCustomers.per_page"
                :total-items="billingCustomers.total"
                :page="billingCustomers.current_page"
                @page-changed="goToPage"
            />
        </div>

        <Dialog v-model:open="syncDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Créer depuis un membre</DialogTitle>
                    <DialogDescription>
                        Le client de facturation sera créé avec les informations du membre. Vous pourrez le modifier ensuite.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="member_id">Membre</Label>
                    <Select v-model="syncForm.member_id">
                        <SelectTrigger id="member_id">
                            <SelectValue placeholder="Sélectionner un membre" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="member in members" :key="member.id" :value="String(member.id)">
                                {{ member.first_name }} {{ member.last_name }}
                                <span v-if="member.email">· {{ member.email }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="syncForm.errors.member_id" class="text-destructive text-sm">{{ syncForm.errors.member_id }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="syncDialogOpen = false">Annuler</Button>
                    <Button :disabled="syncForm.processing || !syncForm.member_id" @click="submitSync">Créer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
