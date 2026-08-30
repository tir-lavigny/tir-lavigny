<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedVouchers, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bons', href: route('bo.register.vouchers.index') },
];

const props = defineProps<{
    vouchers: PaginatedVouchers;
}>();

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.register.vouchers.index'), { page: p }, { preserveState: true, preserveScroll: true });
};

const handleDelete = (id: number) => {
    useForm({}).delete(route('bo.register.vouchers.destroy', id), {
        onSuccess: () => toast.success('Bon supprimé.'),
        onError: () => toast.error('Erreur lors de la suppression.'),
    });
};

const formatDiscount = (voucher: any) => {
    if (voucher.discount_percent) return `${voucher.discount_percent}%`;
    if (voucher.discount_fixed) return `${voucher.discount_fixed.toFixed(2)} CHF`;
    return '—';
};
</script>

<template>
    <Head title="Bons" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Bons de réduction</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage vouchers')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.vouchers.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Créer un bon</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <div v-for="voucher in vouchers.data" :key="voucher.id" class="mb-4 flex items-center justify-between rounded-lg border p-4">
                    <div>
                        <p class="font-medium">
                            {{ formatDiscount(voucher) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <span v-if="voucher.product">{{ voucher.product.name }}</span>
                            <span v-if="voucher.member"> · {{ voucher.member.first_name }} {{ voucher.member.last_name }}</span>
                            <span v-if="voucher.season"> · {{ voucher.season.name }}</span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Tooltip v-if="hasPermissionTo(user, 'manage vouchers')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.vouchers.edit', voucher.id)">
                                    <Button size="icon" variant="outline"><Pencil /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Modifier</p></TooltipContent>
                        </Tooltip>
                        <AppAlertDialog v-if="hasPermissionTo(user, 'manage vouchers')" title="Supprimer ce bon ?" description="Cette action est irréversible.">
                            <template #trigger>
                                <Button size="icon" variant="destructive"><Trash2 /></Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleDelete(voucher.id)">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </div>
                </div>
                <Muted v-if="vouchers.data?.length" class="my-4 flex items-center justify-center">
                    {{ vouchers.total }} bon(s) trouvé(s)
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucun bon trouvé</Muted>
                <AppPagination
                    :items-per-page="vouchers.per_page"
                    :total-items="vouchers.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="vouchers.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
