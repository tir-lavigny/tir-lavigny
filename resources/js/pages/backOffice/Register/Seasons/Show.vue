<script setup lang="ts">
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasPermissionTo } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { BreadcrumbItem, Season, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Ticket } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

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

const props = defineProps<{ season: Season }>();

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
                    <TooltipContent><p>Générer le paket de bons pour tous les membres</p></TooltipContent>
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
                            <dd class="font-medium">{{ season.end_balance != null ? formatMoney(season.end_balance) : '—' }} CHF</dd>
                        </div>
                    </dl>
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

        <Dialog v-model:open="showPreviewDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Aperçu des bons à générer</DialogTitle>
                    <DialogDescription>
                        <template v-if="previewData.length > 0">
                            {{ totalMembersImpacted }} / {{ totalMembers }} membre(s) recevront des bons.
                        </template>
                        <template v-else>
                            Tous les membres ont déjà leur paket de bons complet.
                        </template>
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
                                <TableRow v-for="(voucher, vIndex) in memberPlan.vouchers" :key="`${memberPlan.member_id}-${vIndex}`">
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
