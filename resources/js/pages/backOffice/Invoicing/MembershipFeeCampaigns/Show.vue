<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type {
    BreadcrumbItem,
    MembershipFeeCampaign,
    MembershipFeeCampaignActivityLog,
    MembershipFeeCampaignMember,
    PaginatedMembershipFeeCampaignMembers,
    SharedData,
} from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, FileText, Pencil, RefreshCw, Send, Users, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    campaign: MembershipFeeCampaign;
    members: PaginatedMembershipFeeCampaignMembers;
    filters: { search?: string | null; member_status?: string | null };
    summary: {
        total: number;
        included: number;
        pending: number;
        validated: number;
        excluded: number;
        invoiced: number;
        draft_invoices: number;
        issued_invoices: number;
        base_cents: number;
        discount_cents: number;
        final_cents: number;
    };
    activityLogs: MembershipFeeCampaignActivityLog[];
    memberStatuses: Array<{ value: string; label: string }>;
    deliveryMethods: Array<{ value: string; label: string }>;
    invoicingConfigured: boolean;
    canIssueInvoices: boolean;
}>();

const page = usePage<SharedData>();
const canManage = computed(() => hasAnyPermission(page.props.auth.user, ['manage membership fees', 'manage invoicing']));
const editable = computed(() => ['draft', 'preview', 'validated'].includes(props.campaign.status));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campagnes de cotisation', href: route('bo.invoicing.membership-fee-campaigns.index') },
    { title: props.campaign.name, href: route('bo.invoicing.membership-fee-campaigns.show', props.campaign.id) },
];

const filters = ref({
    search: props.filters.search || '',
    member_status: props.filters.member_status || '',
});

const memberStatusFilter = computed({
    get: () => filters.value.member_status || 'all',
    set: (value: string) => {
        filters.value.member_status = value === 'all' ? '' : value;
        applyFilters(1);
    },
});

const applyFilters = (pageNumber = 1): void => {
    router.get(
        route('bo.invoicing.membership-fee-campaigns.show', props.campaign.id),
        { page: pageNumber, ...filters.value },
        { preserveState: true, preserveScroll: true },
    );
};

const goToPage = (pageNumber: number): void => applyFilters(pageNumber);

const statusLabel = (value: string): string => {
    const labels: Record<string, string> = {
        draft: 'Brouillon',
        preview: 'Aperçu',
        validated: 'Validée',
        invoiced: 'Facturée',
        issued: 'Émise',
        cancelled: 'Annulée',
    };

    return labels[value] ?? value;
};

const memberStatusLabel = (value: string): string => props.memberStatuses.find((status) => status.value === value)?.label ?? value;
const deliveryLabel = (value: string): string => props.deliveryMethods.find((method) => method.value === value)?.label ?? value;
const memberName = (member: MembershipFeeCampaignMember): string => {
    const value = member.member;

    return value ? `${value.first_name} ${value.last_name}`.trim() : `Membre #${member.member_id}`;
};

const runAction = (routeName: string, fallback: string): void => {
    router.post(
        route(routeName, props.campaign.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) => toast.error((errors as Record<string, string>).action ?? fallback),
        },
    );
};

const preview = () => runAction('bo.invoicing.membership-fee-campaigns.preview', 'Erreur lors de la génération de l’aperçu.');
const validateCampaign = () => runAction('bo.invoicing.membership-fee-campaigns.validate', 'Erreur lors de la validation.');
const generateInvoices = () => runAction('bo.invoicing.membership-fee-campaigns.invoices.generate', 'Erreur lors de la génération des factures.');
const issueInvoices = () => runAction('bo.invoicing.membership-fee-campaigns.invoices.issue', 'Erreur lors de l’émission des factures.');

const validateMember = (member: MembershipFeeCampaignMember): void => {
    router.post(
        route('bo.invoicing.membership-fee-campaigns.members.validate', [props.campaign.id, member.id]),
        {},
        {
            preserveScroll: true,
            onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Erreur lors de la validation.'),
        },
    );
};

const excludeMember = (member: MembershipFeeCampaignMember): void => {
    router.post(
        route('bo.invoicing.membership-fee-campaigns.members.exclude', [props.campaign.id, member.id]),
        {},
        {
            preserveScroll: true,
            onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Erreur lors de l’exclusion.'),
        },
    );
};

const includeMember = (member: MembershipFeeCampaignMember): void => {
    router.post(
        route('bo.invoicing.membership-fee-campaigns.members.include', [props.campaign.id, member.id]),
        {},
        {
            preserveScroll: true,
            onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Erreur lors de la réintégration.'),
        },
    );
};

const discountOpen = ref(false);
const discountTarget = ref<MembershipFeeCampaignMember | null>(null);
const discountForm = useForm({ discount: '', reason: '' });
const discountActionError = computed(() => (discountForm.errors as Record<string, string>).action);

const openDiscount = (member: MembershipFeeCampaignMember): void => {
    discountTarget.value = member;
    discountForm.discount = member.discount_amount;
    discountForm.reason = member.discount_reason ?? '';
    discountForm.clearErrors();
    discountOpen.value = true;
};

const submitDiscount = (): void => {
    if (!discountTarget.value) {
        return;
    }

    discountForm.put(route('bo.invoicing.membership-fee-campaigns.members.discount', [props.campaign.id, discountTarget.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            discountOpen.value = false;
            toast.success('Remise mise à jour.');
        },
        onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Vérifiez la remise.'),
    });
};

const deliveryOpen = ref(false);
const deliveryTarget = ref<MembershipFeeCampaignMember | null>(null);
const deliveryForm = useForm({ delivery_method: 'email' });
const deliveryActionError = computed(() => (deliveryForm.errors as Record<string, string>).action);

const openDelivery = (member: MembershipFeeCampaignMember): void => {
    deliveryTarget.value = member;
    deliveryForm.delivery_method = member.delivery_method;
    deliveryForm.clearErrors();
    deliveryOpen.value = true;
};

const submitDelivery = (): void => {
    if (!deliveryTarget.value) {
        return;
    }

    deliveryForm.put(route('bo.invoicing.membership-fee-campaigns.members.delivery', [props.campaign.id, deliveryTarget.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            deliveryOpen.value = false;
            toast.success('Mode d’envoi mis à jour.');
        },
        onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Vérifiez le mode d’envoi.'),
    });
};
</script>

<template>
    <Head :title="campaign.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <H1>{{ campaign.name }}</H1>
                    <Badge variant="secondary">{{ statusLabel(campaign.status) }}</Badge>
                </div>
                <p class="text-muted-foreground mt-2 text-sm">
                    Année {{ campaign.year }} · Émission {{ formatDate(campaign.issue_date, 'DD.MM.YYYY') }} · Échéance
                    {{ formatDate(campaign.due_date, 'DD.MM.YYYY') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button v-if="canManage && editable" variant="outline" @click="preview">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    {{ campaign.status === 'draft' ? 'Générer l’aperçu' : 'Actualiser l’aperçu' }}
                </Button>
                <Button v-if="canManage && campaign.status === 'preview'" variant="outline" @click="validateCampaign">
                    <CheckCircle2 class="mr-2 h-4 w-4" /> Tout valider
                </Button>
                <Button v-if="canManage && campaign.status === 'validated'" :disabled="!invoicingConfigured" @click="generateInvoices">
                    <FileText class="mr-2 h-4 w-4" /> Générer les brouillons
                </Button>
                <Button v-if="canIssueInvoices && campaign.status === 'invoiced'" :disabled="!invoicingConfigured" @click="issueInvoices">
                    <Send class="mr-2 h-4 w-4" /> Émettre les factures
                </Button>
                <Button v-if="canManage && editable" variant="outline" as-child>
                    <Link :href="route('bo.invoicing.membership-fee-campaigns.edit', campaign.id)"> <Pencil class="mr-2 h-4 w-4" /> Modifier </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="!invoicingConfigured && ['validated', 'invoiced'].includes(campaign.status)"
            class="border-destructive/40 bg-destructive/5 mx-4 rounded-xl border p-4 text-sm"
        >
            Les paramètres de facturation sont incomplets. La génération et l’émission des factures sont bloquées.
        </div>

        <div class="mx-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <p class="text-muted-foreground text-sm">Membres inclus</p>
                <p class="mt-1 text-2xl font-semibold">{{ summary.included }}</p>
                <p class="text-muted-foreground mt-1 text-xs">{{ summary.excluded }} exclu(s) sur {{ summary.total }}</p>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <p class="text-muted-foreground text-sm">Validation</p>
                <p class="mt-1 text-2xl font-semibold">{{ summary.validated }} / {{ summary.included }}</p>
                <p class="text-muted-foreground mt-1 text-xs">{{ summary.pending }} en attente</p>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <p class="text-muted-foreground text-sm">Factures</p>
                <p class="mt-1 text-2xl font-semibold">{{ summary.invoiced }} / {{ summary.included }}</p>
                <p class="text-muted-foreground mt-1 text-xs">{{ summary.issued_invoices }} émise(s) · {{ summary.draft_invoices }} brouillon(s)</p>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <p class="text-muted-foreground text-sm">Total à facturer</p>
                <p class="mt-1 font-mono text-2xl font-semibold">CHF {{ formatMoney(summary.final_cents / 100) }}</p>
                <p class="text-muted-foreground mt-1 text-xs">
                    Base {{ formatMoney(summary.base_cents / 100) }} · Remises {{ formatMoney(summary.discount_cents / 100) }}
                </p>
            </div>
        </div>

        <div class="mx-4 mt-4 flex flex-col gap-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-end">
                <div class="grid flex-1 gap-2">
                    <Label for="search">Rechercher un membre</Label>
                    <Input id="search" v-model="filters.search" placeholder="Nom ou email" @keyup.enter="applyFilters(1)" />
                </div>
                <div class="grid gap-2 md:w-56">
                    <Label for="member_status">Validation</Label>
                    <Select v-model="memberStatusFilter">
                        <SelectTrigger id="member_status" class="w-full">
                            <SelectValue placeholder="Tous" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem v-for="status in memberStatuses" :key="status.value" :value="status.value">{{ status.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Membre</th>
                            <th class="px-4 py-3 text-left font-medium">Type</th>
                            <th class="px-4 py-3 text-right font-medium">Base</th>
                            <th class="px-4 py-3 text-right font-medium">Remise</th>
                            <th class="px-4 py-3 text-right font-medium">Final</th>
                            <th class="px-4 py-3 text-left font-medium">Envoi</th>
                            <th class="px-4 py-3 text-left font-medium">Statut</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="member in members.data"
                            :key="member.id"
                            class="border-sidebar-border/70 dark:border-sidebar-border border-t align-top"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ memberName(member) }}</div>
                                <div class="text-muted-foreground">{{ member.member?.email || '—' }}</div>
                                <div v-if="member.invoice" class="mt-1">
                                    <Link
                                        :href="route('bo.invoicing.invoices.show', member.invoice.id)"
                                        class="text-primary text-xs underline underline-offset-2"
                                    >
                                        {{ member.invoice.invoice_number ?? 'Facture brouillon' }}
                                    </Link>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ member.fee_type_name || member.fee_type?.name || '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(member.base_amount) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-mono">{{ formatMoney(member.discount_amount) }}</div>
                                <Badge v-if="member.is_full_exemption" variant="destructive" class="mt-1">Exonération totale</Badge>
                                <div v-if="member.discount_reason" class="text-muted-foreground mt-1 max-w-48 text-xs">
                                    {{ member.discount_reason }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium">{{ formatMoney(member.final_amount) }}</td>
                            <td class="px-4 py-3">{{ deliveryLabel(member.delivery_method) }}</td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="member.status === 'excluded' ? 'outline' : member.status === 'validated' ? 'secondary' : 'destructive'"
                                >
                                    {{ memberStatusLabel(member.status) }}
                                </Badge>
                                <div v-if="member.validator" class="text-muted-foreground mt-1 text-xs">par {{ member.validator.name }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    <template v-if="canManage && editable && member.invoice_id === null">
                                        <Button v-if="member.status !== 'excluded'" size="sm" variant="outline" @click="openDiscount(member)"
                                            >Remise</Button
                                        >
                                        <Button v-if="member.status !== 'excluded'" size="sm" variant="outline" @click="openDelivery(member)"
                                            >Envoi</Button
                                        >
                                        <Button v-if="member.status === 'pending'" size="sm" variant="outline" @click="validateMember(member)">
                                            Valider
                                        </Button>
                                        <Button v-if="member.status !== 'excluded'" size="sm" variant="outline" @click="excludeMember(member)">
                                            <XCircle class="h-4 w-4" />
                                        </Button>
                                        <Button v-if="member.status === 'excluded'" size="sm" variant="outline" @click="includeMember(member)">
                                            <Users class="h-4 w-4" />
                                        </Button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!members.data?.length">
                            <td colspan="8" class="text-muted-foreground px-4 py-8 text-center">
                                Aucun membre. Générez l’aperçu pour calculer les cotisations.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination :items-per-page="members.per_page" :total-items="members.total" :page="members.current_page" @page-changed="goToPage" />

            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Historique de la campagne</H3>
                <ul class="mt-3 flex flex-col gap-3">
                    <li
                        v-for="log in activityLogs"
                        :key="log.id"
                        class="border-sidebar-border/70 dark:border-sidebar-border border-b pb-3 last:border-0 last:pb-0"
                    >
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium">{{ log.action_label || log.action }}</span>
                            <span v-if="log.campaign_member?.member" class="text-muted-foreground">
                                · {{ log.campaign_member.member.first_name }} {{ log.campaign_member.member.last_name }}
                            </span>
                            <span class="text-muted-foreground">· {{ log.user?.name || 'Système' }}</span>
                            <span class="text-muted-foreground">· {{ formatDate(log.created_at, 'DD.MM.YYYY HH:mm') }}</span>
                        </div>
                    </li>
                    <li v-if="!activityLogs.length" class="text-muted-foreground text-sm">Aucune activité enregistrée.</li>
                </ul>
            </div>
        </div>

        <Dialog v-model:open="discountOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remise individuelle</DialogTitle>
                    <DialogDescription>
                        {{ discountTarget ? memberName(discountTarget) : '' }} · cotisation de
                        {{ discountTarget ? formatMoney(discountTarget.base_amount) : '0.00' }} CHF
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submitDiscount">
                    <div class="grid gap-2">
                        <Label for="discount">Montant de la remise (CHF)</Label>
                        <Input id="discount" v-model="discountForm.discount" inputmode="decimal" placeholder="0.00" />
                        <p v-if="discountForm.errors.discount" class="text-destructive text-sm">{{ discountForm.errors.discount }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="reason">Motif</Label>
                        <Textarea id="reason" v-model="discountForm.reason" rows="3" placeholder="Motif de la remise ou de l’exonération" />
                        <p class="text-muted-foreground text-xs">Obligatoire pour une exonération totale.</p>
                        <p v-if="discountForm.errors.reason" class="text-destructive text-sm">{{ discountForm.errors.reason }}</p>
                    </div>
                    <p v-if="discountActionError" class="text-destructive text-sm">{{ discountActionError }}</p>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="discountOpen = false">Annuler</Button>
                        <Button type="submit" :disabled="discountForm.processing">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deliveryOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mode d’envoi</DialogTitle>
                    <DialogDescription>{{ deliveryTarget ? memberName(deliveryTarget) : '' }}</DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submitDelivery">
                    <div class="grid gap-2">
                        <Label for="delivery_method">Mode d’envoi</Label>
                        <Select v-model="deliveryForm.delivery_method">
                            <SelectTrigger id="delivery_method" class="w-full">
                                <SelectValue placeholder="Sélectionner" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="method in deliveryMethods" :key="method.value" :value="method.value">{{
                                    method.label
                                }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="deliveryForm.errors.delivery_method" class="text-destructive text-sm">{{ deliveryForm.errors.delivery_method }}</p>
                    </div>
                    <p v-if="deliveryActionError" class="text-destructive text-sm">{{ deliveryActionError }}</p>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="deliveryOpen = false">Annuler</Button>
                        <Button type="submit" :disabled="deliveryForm.processing">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
