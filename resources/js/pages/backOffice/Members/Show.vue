<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, Member, MemberRegistration, SharedData, Voucher } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { RefreshCw, Trash2, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface MemberWithDetails extends Member {
    vouchers?: Voucher[];
    registration?: MemberRegistration;
}

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    member: MemberWithDetails;
    seasons: import('@/types').Season[];
    vouchers: Voucher[];
    usedVouchers: Voucher[];
}>();

const member = computed(() => props.member);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Membres', href: route('bo.members.index') },
    { title: member.value.first_name + ' ' + member.value.last_name, href: route('bo.members.show', member.value.id) },
];

const selectedSeasonId = ref<number | null>(props.seasons[0]?.id ?? null);
const selectedSeason = computed(() => props.seasons.find((s) => s.id === selectedSeasonId.value) || null);
const filteredVouchers = computed(() => props.vouchers.filter((v) => v.season_id === selectedSeasonId.value));
const unusedVouchers = computed(() => filteredVouchers.value.filter((v) => !props.usedVouchers.some((uv) => uv.id === v.id)));

const showAvs = ref(false);
const confirmation = ref('');

const handleSync = () => {
    useForm({}).post(route('bo.members.sync', member.value.id), {
        onSuccess: () => toast.success('Synchronisation effectuée.'),
        onError: () => toast.error('Erreur lors de la synchronisation.'),
    });
};

const handleArchive = () => {
    useForm({}).delete(route('bo.members.destroy', member.value.id), {
        onSuccess: () => toast.success('Membre archivé.'),
        onError: () => toast.error("Erreur lors de l'archivage."),
    });
};

const handleRestore = () => {
    useForm({}).post(route('bo.members.restore', member.value.id), {
        onSuccess: () => toast.success('Membre restauré.'),
        onError: () => toast.error('Erreur lors de la restauration.'),
    });
};

const handleForceDelete = () => {
    useForm({ confirmation: confirmation.value }).delete(route('bo.members.force-delete', member.value.id), {
        onSuccess: () => toast.success('Membre supprimé.'),
        onError: () => toast.error('La confirmation est incorrecte.'),
    });
};

const handleRemoveUnused = () => {
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
};
</script>

<template>
    <Head :title="member.first_name + ' ' + member.last_name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex w-full flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <H1>{{ member.first_name }} {{ member.last_name }}</H1>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="member.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >
                        {{ member.status === 'active' ? 'Actif' : 'Passif' }}
                    </span>
                    <span v-if="member.deleted_at" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">Archivé</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" @click="handleSync"><RefreshCw class="mr-2 h-4 w-4" /> Synchroniser</Button>
                    <Button v-if="member.deleted_at" variant="outline" @click="handleRestore"><Undo2 class="mr-2 h-4 w-4" /> Restaurer</Button>
                    <AppAlertDialog v-if="!member.deleted_at" title="Archiver ce membre ?" description="Le membre sera archivé." cancelText="Annuler">
                        <template #trigger>
                            <Button variant="destructive"><Trash2 class="mr-2 h-4 w-4" /> Archiver</Button>
                        </template>
                        <template #action>
                            <Button variant="destructive" @click="handleArchive">Archiver</Button>
                        </template>
                    </AppAlertDialog>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border p-4">
                <H3>Informations</H3>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label>Email</Label>
                        <Muted>{{ member.email_masked || '—' }}</Muted>
                    </div>
                    <div>
                        <Label>Téléphone</Label>
                        <Muted>{{ member.tel || '—' }}</Muted>
                    </div>
                    <div>
                        <Label>AVS</Label>
                        <div class="flex items-center gap-2">
                            <Muted>{{ showAvs ? member.avs || '—' : member.avs_masked || '—' }}</Muted>
                            <Button
                                v-if="member.avs && hasPermissionTo(user, 'view member sensitive data')"
                                size="sm"
                                variant="outline"
                                @click="showAvs = !showAvs"
                            >
                                {{ showAvs ? 'Masquer' : 'Afficher' }}
                            </Button>
                        </div>
                    </div>
                    <div>
                        <Label>Citoyen suisse</Label>
                        <Muted>{{ member.is_swiss_citizen ? 'Oui' : 'Non' }}</Muted>
                    </div>
                    <div>
                        <Label>Adresse</Label>
                        <Muted>{{ member.street || '—' }} {{ member.npa || '' }} {{ member.city || '' }} {{ member.canton || '' }}</Muted>
                    </div>
                    <div>
                        <Label>Pays</Label>
                        <Muted>{{ member.country || '—' }}</Muted>
                    </div>
                    <div>
                        <Label>DAV</Label>
                        <Muted>{{ member.dav_uid || 'Non lié' }}</Muted>
                        <Muted v-if="member.dav_sync_error" class="text-red-600">Erreur : {{ member.dav_sync_error }}</Muted>
                    </div>
                </div>

                <div v-if="member.registration" class="mt-6">
                    <H3>Inscription</H3>
                    <div class="mt-2">
                        <Muted>{{ member.registration.form_submitted_at || '—' }}</Muted>
                    </div>
                </div>

                <div class="mt-6">
                    <H3>Bons</H3>
                    <div class="mt-2 grid max-w-sm grid-cols-4 items-center gap-4">
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
                    <div v-if="filteredVouchers.length" class="mt-2 flex flex-col gap-2">
                        <div v-for="voucher in filteredVouchers" :key="voucher.id" class="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <p class="font-medium">{{ voucher.product?.name || 'Produit #' + voucher.product_id }}</p>
                                <p v-if="usedVouchers.some((uv) => uv.id === voucher.id)" class="text-muted-foreground text-sm">Utilisé</p>
                                <p v-else class="text-muted-foreground text-sm">Non utilisé</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground mt-2 text-sm">Aucun bon pour cette saison.</p>
                    <AppAlertDialog
                        v-if="selectedSeason && unusedVouchers.length > 0 && hasPermissionTo(user, 'manage vouchers')"
                        title="Supprimer les bons inutilisés ?"
                        :description="unusedVouchers.length + ' bon(s) inutilisé(s). Cette action est irréversible.'"
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

                <div class="mt-6">
                    <H3>Suppression définitive</H3>
                    <Muted class="mt-2">
                        Cette action est irréversible. Si le membre possède des bons ou des tickets, ses données personnelles seront supprimées et le
                        membre sera anonymisé.
                    </Muted>
                    <div class="mt-4 grid max-w-md gap-2">
                        <Label for="confirmation">Saisissez le nom complet du membre pour confirmer</Label>
                        <Input id="confirmation" v-model="confirmation" type="text" :placeholder="member.first_name + ' ' + member.last_name" />
                        <AppAlertDialog
                            title="Supprimer définitivement ce membre ?"
                            description="Cette action est irréversible."
                            cancelText="Annuler"
                        >
                            <template #trigger>
                                <Button variant="destructive" class="w-fit">Supprimer définitivement</Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleForceDelete">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
