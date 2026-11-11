<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasPermissionTo, parseDateOnly } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { BreadcrumbItem, CashMovement, Season, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDownToLine, ArrowUpFromLine, Info, Landmark, Pencil, Ticket, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface BalanceSummary {
    initial_balance: number;
    sales_total: number;
    movements_total: number;
    last_day_balance: number;
    post_last_day_movements: number;
    end_balance: number;
}

interface VoucherPlan {
    product_name: string;
    count: number;
}

interface MemberPlan {
    member_id: number;
    member_name: string;
    vouchers: VoucherPlan[];
}

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{ season: Season; balanceSummary: BalanceSummary }>();

const canManageCashMovements = computed(() => hasPermissionTo(user, 'manage cash movements'));

const movements = computed(() => props.season.cash_movements ?? []);

const seasonEndDate = computed(() => props.season.end_date.slice(0, 10));
const movementMaxDate = computed(() => {
    const today = localToday();
    return seasonEndDate.value < today ? seasonEndDate.value : today;
});

const movementForm = useForm({
    direction: 'out' as 'in' | 'out',
    amount: '' as string | number,
    occurred_at: movementMaxDate.value,
    remark: '',
});

const showMovementDialog = ref(false);
const editedMovementId = ref<number | null>(null);

function localToday(): string {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function openMovementDialog(direction: 'in' | 'out', movement?: CashMovement) {
    editedMovementId.value = movement?.id ?? null;
    movementForm.clearErrors();
    movementForm.direction = direction;
    movementForm.amount = movement ? Math.abs(Number(movement.amount)) : '';
    movementForm.occurred_at = movement?.occurred_at?.slice(0, 10) ?? movementMaxDate.value;
    movementForm.remark = movement?.remark ?? '';
    showMovementDialog.value = true;
}

function submitMovement() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMovementDialog.value = false;
            toast.success('Mouvement de caisse enregistré.');
        },
        onError: () => toast.error("Erreur lors de l'enregistrement du mouvement."),
    };

    if (editedMovementId.value !== null) {
        movementForm.put(route('bo.register.seasons.cash-movements.update', [props.season.id, editedMovementId.value]), options);
        return;
    }

    movementForm.post(route('bo.register.seasons.cash-movements.store', props.season.id), options);
}

function deleteMovement(movement: CashMovement) {
    useForm({}).delete(route('bo.register.seasons.cash-movements.destroy', [props.season.id, movement.id]), {
        preserveScroll: true,
        onSuccess: () => toast.success('Mouvement de caisse supprimé.'),
        onError: () => toast.error('Erreur lors de la suppression du mouvement.'),
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Saisons', href: route('bo.register.seasons.index') },
    { title: props.season.name, href: route('bo.register.seasons.show', props.season.id) },
];

const showPreviewDialog = ref(false);
const previewData = ref<MemberPlan[]>([]);
const loading = ref(false);
const generating = ref(false);

const totalMembersImpacted = ref(0);
const totalMembers = ref(0);

async function fetchPreview() {
    loading.value = true;
    try {
        const response = await fetch(route('bo.register.seasons.preview-base-vouchers', props.season.id), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
        });
        const data = await response.json();
        previewData.value = data.plan;
        totalMembers.value = data.total_members;

        const memberIds = new Set(data.plan.map((p: MemberPlan) => p.member_id));
        totalMembersImpacted.value = memberIds.size;

        showPreviewDialog.value = true;
    } catch {
        toast.error("Erreur lors du chargement de l'aperçu.");
    } finally {
        loading.value = false;
    }
}

const form = useForm({});

function confirmGenerate() {
    generating.value = true;
    form.post(route('bo.register.seasons.generate-base-vouchers', props.season.id), {
        onSuccess: () => {
            toast.success('Bons de base générés avec succès.');
            showPreviewDialog.value = false;
            previewData.value = [];
        },
        onError: () => {
            toast.error('Erreur lors de la génération des bons.');
        },
        onFinish: () => {
            generating.value = false;
        },
    });
}
</script>

<template>
    <Head :title="season.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>{{ season.name }}</H1>
            <div v-if="hasPermissionTo(user, 'manage seasons')" class="flex items-center gap-2">
                <Tooltip>
                    <TooltipTrigger>
                        <Button :loading="loading" variant="outline" @click="fetchPreview">
                            <Ticket class="mr-1 h-4 w-4" /> Générer les bons de base
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Générer le paquet de bons pour tous les membres</p></TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger>
                        <Link :href="route('bo.register.seasons.edit', season.id)">
                            <Button size="icon" variant="outline"><Pencil /></Button>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent><p>Modifier</p></TooltipContent>
                </Tooltip>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Détails</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Date de début</dt>
                            <dd class="font-medium">{{ formatDate(season.start_date, 'DD.MM.YYYY') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Date de fin</dt>
                            <dd class="font-medium">{{ formatDate(season.end_date, 'DD.MM.YYYY') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Solde initial</dt>
                            <dd class="font-medium">{{ formatMoney(season.initial_balance) }} CHF</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Solde final</dt>
                            <dd class="font-medium">{{ formatMoney(balanceSummary.end_balance) }} CHF</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <CardTitle>Mouvements de caisse</CardTitle>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="text-muted-foreground inline-flex cursor-help items-center"
                                    aria-label="Aide sur les mouvements de caisse"
                                >
                                    <Info class="size-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs">
                                <p>
                                    Transferts d'argent entre la caisse et la banque, indépendants des ventes. Un retrait diminue le solde de la
                                    première journée à partir de la date choisie, une injection l'augmente. La banque n'est pas suivie.
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <Button v-if="canManageCashMovements" variant="outline" @click="openMovementDialog('out')">
                        <Landmark class="mr-2 size-4" /> Nouveau mouvement
                    </Button>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-muted-foreground text-sm">Ventes espèces</dt>
                            <dd class="font-medium">{{ formatMoney(balanceSummary.sales_total) }} CHF</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Mouvements de caisse</dt>
                            <dd class="font-medium">{{ formatMoney(balanceSummary.movements_total) }} CHF</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm">Solde dernière journée</dt>
                            <dd class="font-medium">{{ formatMoney(balanceSummary.last_day_balance) }} CHF</dd>
                        </div>
                        <div v-if="balanceSummary.post_last_day_movements !== 0">
                            <dt class="text-muted-foreground flex items-center gap-1 text-sm">
                                Mouvements après la dernière journée
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex cursor-help items-center"
                                            aria-label="Aide sur les mouvements après la dernière journée"
                                        >
                                            <Info class="size-4" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent class="max-w-xs">
                                        <p>
                                            Mouvements datés après la dernière journée d'opération de la saison. Ils ne modifient pas le solde de
                                            cette journée, mais entrent dans le solde final.
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                            </dt>
                            <dd class="font-medium">{{ formatMoney(balanceSummary.post_last_day_movements) }} CHF</dd>
                        </div>
                    </dl>

                    <Table v-if="movements.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="text-right">Montant</TableHead>
                                <TableHead>Remarque</TableHead>
                                <TableHead v-if="canManageCashMovements" class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="movement in movements" :key="movement.id">
                                <TableCell>{{ formatDate(parseDateOnly(movement.occurred_at), 'DD.MM.YYYY') }}</TableCell>
                                <TableCell>
                                    <Badge :variant="Number(movement.amount) < 0 ? 'secondary' : 'default'">
                                        {{ Number(movement.amount) < 0 ? 'Retrait' : 'Injection' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">{{ formatMoney(movement.amount) }} CHF</TableCell>
                                <TableCell>{{ movement.remark || '—' }}</TableCell>
                                <TableCell v-if="canManageCashMovements" class="flex justify-end gap-2">
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        @click="openMovementDialog(Number(movement.amount) < 0 ? 'out' : 'in', movement)"
                                    >
                                        <Pencil />
                                    </Button>
                                    <AppAlertDialog
                                        title="Supprimer ce mouvement ?"
                                        description="Le solde des journées suivantes sera recalculé."
                                        cancelText="Annuler"
                                    >
                                        <template #trigger>
                                            <Button size="icon" variant="destructive"><Trash2 /></Button>
                                        </template>
                                        <template #action>
                                            <Button variant="destructive" @click="deleteMovement(movement)">Supprimer</Button>
                                        </template>
                                    </AppAlertDialog>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="text-muted-foreground text-sm">Aucun mouvement de caisse.</p>
                </CardContent>
            </Card>

            <Separator />

            <div class="flex items-center justify-between">
                <H3>Journées d'opération</H3>
                <Badge variant="secondary">{{ season.operating_days?.length ?? 0 }}</Badge>
            </div>

            <div v-if="season.operating_days?.length" class="flex flex-col gap-2">
                <Link
                    v-for="day in season.operating_days"
                    :key="day.id"
                    :href="route('bo.register.operating-days.show', day.id)"
                    class="hover:bg-accent flex items-center justify-between rounded-lg border p-4"
                >
                    <div>
                        <p class="font-medium">{{ formatDate(day.date, 'DD.MM.YYYY') }}</p>
                        <p class="text-muted-foreground text-sm">
                            Espèces: {{ formatMoney(day.total_cash) }} CHF | Twint: {{ formatMoney(day.total_twint) }} CHF
                        </p>
                    </div>
                    <Badge :variant="day.closed_at ? 'secondary' : 'default'">
                        {{ day.closed_at ? 'Fermé' : 'Ouvert' }}
                    </Badge>
                </Link>
            </div>
            <p v-else class="text-muted-foreground text-sm">Aucune journée d'opération.</p>
        </div>

        <Dialog v-model:open="showMovementDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ movementForm.direction === 'out' ? 'Retrait vers la banque' : 'Injection depuis la banque' }}</DialogTitle>
                    <DialogDescription>
                        Le montant est pris en compte dans le solde de la première journée à partir de la date choisie.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Type</Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                :variant="movementForm.direction === 'in' ? 'default' : 'outline'"
                                @click="movementForm.direction = 'in'"
                            >
                                <ArrowDownToLine class="mr-2 size-4" /> Injection
                            </Button>
                            <Button
                                type="button"
                                :variant="movementForm.direction === 'out' ? 'default' : 'outline'"
                                @click="movementForm.direction = 'out'"
                            >
                                <ArrowUpFromLine class="mr-2 size-4" /> Retrait
                            </Button>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="movement-amount">Montant (CHF)</Label>
                        <Input id="movement-amount" v-model="movementForm.amount" type="number" step="0.01" min="0.01" />
                        <p v-if="movementForm.errors.amount" class="text-destructive text-sm">{{ movementForm.errors.amount }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="movement-date">Date</Label>
                        <Input
                            id="movement-date"
                            v-model="movementForm.occurred_at"
                            type="date"
                            :min="season.start_date.slice(0, 10)"
                            :max="movementMaxDate"
                        />
                        <p v-if="movementForm.errors.occurred_at" class="text-destructive text-sm">{{ movementForm.errors.occurred_at }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="movement-remark">Remarque</Label>
                        <Input id="movement-remark" v-model="movementForm.remark" maxlength="255" />
                        <p v-if="movementForm.errors.remark" class="text-destructive text-sm">{{ movementForm.errors.remark }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" :disabled="movementForm.processing" @click="showMovementDialog = false">Annuler</Button>
                    <Button :disabled="movementForm.processing" @click="submitMovement">Enregistrer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showPreviewDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Aperçu des bons à générer</DialogTitle>
                    <DialogDescription>
                        <template v-if="previewData.length > 0">
                            {{ totalMembersImpacted }} / {{ totalMembers }} membre(s) recevront des bons.
                        </template>
                        <template v-else> Tous les membres ont déjà leur paquet de bons complet. </template>
                    </DialogDescription>
                </DialogHeader>
                <div v-if="previewData.length > 0" class="max-h-96 overflow-y-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Membre</TableHead>
                                <TableHead>Produit</TableHead>
                                <TableHead class="text-right">Quantité</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-for="memberPlan in previewData" :key="memberPlan.member_id">
                                <TableRow
                                    v-for="(voucher, vIndex) in memberPlan.vouchers"
                                    :key="`${memberPlan.member_id}-${vIndex}`"
                                    class="hover:bg-background"
                                >
                                    <TableCell v-if="vIndex === 0" class="font-medium" :rowspan="memberPlan.vouchers.length">
                                        {{ memberPlan.member_name }}
                                    </TableCell>
                                    <TableCell>{{ voucher.product_name }}</TableCell>
                                    <TableCell class="text-right">{{ voucher.count }}</TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showPreviewDialog = false">Annuler</Button>
                    <Button :disabled="previewData.length === 0 || generating" :loading="generating" @click="confirmGenerate">
                        <Ticket class="mr-1 h-4 w-4" /> Confirmer la génération
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
