<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Member, Voucher, SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface MemberWithVouchers extends Member {
    vouchers?: Voucher[];
}

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    member: MemberWithVouchers;
    seasons: import('@/types').Season[];
    vouchers: Voucher[];
    usedVouchers: Voucher[];
}>();

const member = computed(() => props.member);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Membres', href: route('bo.register.members.index') },
    { title: member.value.first_name + ' ' + member.value.last_name, href: route('bo.register.members.show', member.value.id) },
];

const selectedSeasonId = ref<number | null>(props.seasons[0]?.id ?? null);

const selectedSeason = computed(() => {
    return props.seasons.find((s) => s.id === selectedSeasonId.value) || null;
});

const isTerminated = computed(() => {
    if (!selectedSeason.value) return false;
    return new Date(selectedSeason.value.end_date) < new Date();
});

const filteredVouchers = computed(() => {
    return props.vouchers.filter((v) => v.season_id === selectedSeasonId.value);
});

const unusedVouchers = computed(() => {
    return filteredVouchers.value.filter((v) => !props.usedVouchers.some((uv) => uv.id === v.id));
});

function handleRemoveUnused() {
    const vouchers = unusedVouchers.value;
    if (vouchers.length === 0) return;

    let completed = 0;
    vouchers.forEach((voucher) => {
        useForm({}).delete(route('bo.register.vouchers.destroy', voucher.id), {
            onSuccess: () => {
                toast.success('Bon supprimé.');
            },
            onError: () => {
                toast.error('Erreur lors de la suppression du bon.');
            },
            onFinish: () => {
                completed++;
                if (completed === vouchers.length) {
                    router.reload({ only: ['vouchers', 'usedVouchers'] });
                }
            },
        });
    });
}
</script>

<template>
    <Head :title="member.first_name + ' ' + member.last_name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex w-full flex-row items-center justify-between">
                <H1>{{ member.first_name }} {{ member.last_name }}</H1>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row items-center justify-between">
                    <H3>Sélectionner une saison</H3>
                </div>
                <div class="grid max-w-sm grid-cols-4 items-center gap-4">
                    <Label for="season" class="text-right">Saison</Label>
                    <Select v-model="selectedSeasonId" id="season" class="col-span-3">
                        <SelectTrigger>
                            <SelectValue placeholder="Sélectionner une saison" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="season in seasons" :key="season.id" :value="season.id">
                                {{ season.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="mt-6 flex items-center justify-between">
                    <H3>Bons</H3>
                    <Muted>{{ filteredVouchers.length }} bon(s)</Muted>
                </div>
                <div v-if="filteredVouchers.length" class="mt-2 flex flex-col gap-2">
                    <div v-for="voucher in filteredVouchers" :key="voucher.id" class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <p class="font-medium">{{ voucher.product?.name || 'Produit #' + voucher.product_id }}</p>
                            <p v-if="usedVouchers.some((uv) => uv.id === voucher.id)" class="text-muted-foreground text-sm">Utilisé</p>
                            <p v-else class="text-muted-foreground text-sm">Non utilisé</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-muted-foreground text-sm">Aucun bon pour cette saison.</p>
                <AppAlertDialog
                    v-if="isTerminated && unusedVouchers.length > 0 && hasPermissionTo(user, 'manage vouchers')"
                    title="Supprimer les bons inutilisés ?"
                    :description="
                        selectedSeason?.name
                            ? unusedVouchers.length +
                              ' bon(s) inutilisé(s) pour la saison ' +
                              selectedSeason.name +
                              '. Cette action est irréversible.'
                            : ''
                    "
                    cancelText="Annuler"
                >
                    <template #trigger>
                        <Button variant="destructive" class="mt-4">
                            <Trash2 class="mr-2 h-4 w-4" /> Supprimer les bons inutilisés ({{ unusedVouchers.length }})
                        </Button>
                    </template>
                    <template #action>
                        <Button variant="destructive" @click="handleRemoveUnused">Supprimer</Button>
                    </template>
                </AppAlertDialog>
            </div>
        </div>
    </AppLayout>
</template>
