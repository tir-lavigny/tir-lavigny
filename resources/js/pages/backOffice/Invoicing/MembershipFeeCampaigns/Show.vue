<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppExportMenu from '@/components/back-office/Invoicing/AppExportMenu.vue';
import AppTooltipIconButton from '@/components/AppTooltipIconButton.vue';
import AppTooltipLabel from '@/components/AppTooltipLabel.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useListFilters } from '@/composables/useListFilters';
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
import { CheckCircle2, FileText, Pencil, RefreshCw, Search, Send, Trash2, UserMinus, UserPlus } from '@lucide/vue';
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
    canRefreshBaseAmounts: boolean;
    canDeleteCampaign: boolean;
}>();

const page = usePage<SharedData>();
const canManage = computed(() => hasAnyPermission(page.props.auth.user, ['manage membership fees', 'manage invoicing']));
const editable = computed(() => ['draft', 'preview', 'validated'].includes(props.campaign.status));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campagnes de cotisation', href: route('bo.invoicing.membership-fee-campaigns.index') },
    { title: props.campaign.name, href: route('bo.invoicing.membership-fee-campaigns.show', props.campaign.id) },
];

const { filters: listFilters, visit } = useListFilters(
    'bo.invoicing.membership-fee-campaigns.show',
    {
        search: props.filters.search || '',
        member_status: props.filters.member_status || '',
    },
    300,
    { membershipFeeCampaign: props.campaign.id },
);

const memberStatusFilter = computed({
    get: () => listFilters.value.member_status || 'all',
    set: (value: string) => {
        listFilters.value.member_status = value === 'all' ? '' : value;
    },
});

const hasActiveFilters = computed(() => Boolean(listFilters.value.search || listFilters.value.member_status));

const goToPage = (pageNumber: number): void => visit(pageNumber);

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

const statusDescription = (value: string): string => {
    const descriptions: Record<string, string> = {
        draft: 'La campagne est créée mais aucun aperçu n’a encore été généré.',
        preview: 'Des membres sont en attente de validation ou de correction.',
        validated: 'Les membres sont validés. Les factures brouillon peuvent être générées.',
        invoiced: 'Les factures brouillon existent mais ne sont pas encore émises.',
        issued: 'Toutes les factures de la campagne ont été émises.',
        cancelled: 'Cette campagne est annulée.',
    };

    return descriptions[value] ?? value;
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

const refreshBaseAmounts = (): void => {
    router.post(
        route('bo.invoicing.membership-fee-campaigns.refresh-base-amounts', props.campaign.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Erreur lors du recalcul.'),
        },
    );
};

const deleteCampaign = (): void => {
    router.delete(route('bo.invoicing.membership-fee-campaigns.destroy', props.campaign.id), {
        preserveScroll: true,
        onError: (errors) => toast.error((errors as Record<string, string>).action ?? 'Erreur lors de la suppression.'),
    });
};

const deleteDescription = computed(() => {
    const draftCount = props.summary.draft_invoices;

    if (draftCount > 0) {
        return `La campagne et ses ${draftCount} facture(s) brouillon seront supprimées. Les clients de facturation seront conservés.`;
    }

    return 'La campagne et ses lignes seront supprimées. Les clients de facturation seront conservés.';
});

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
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Badge variant="secondary" class="cursor-help">{{ statusLabel(campaign.status) }}</Badge>
                        </TooltipTrigger>
                        <TooltipContent>{{ statusDescription(campaign.status) }}</TooltipContent>
                    </Tooltip>
                </div>
                <p class="text-muted-foreground mt-2 text-sm">
                    Année {{ campaign.year }} · Émission {{ formatDate(campaign.issue_date, 'DD.MM.YYYY') }} · Échéance
                    {{ formatDate(campaign.due_date, 'DD.MM.YYYY') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Tooltip v-if="canManage && editable">
                    <TooltipTrigger as-child>
                        <Button variant="outline" @click="preview">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            {{ campaign.status === 'draft' ? 'Générer l’aperçu' : 'Ajouter les membres manquants' }}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {{
                            campaign.status === 'draft'
                                ? 'Calcule la cotisation de chaque membre et crée l’aperçu.'
                                : 'Ajoute les nouveaux membres sans modifier les remises, exclusions et validations existantes.'
                        }}
                    </TooltipContent>
                </Tooltip>
                <Tooltip v-if="canManage && campaign.status === 'preview'">
                    <TooltipTrigger as-child>
                        <Button variant="outline" @click="validateCampaign"> <CheckCircle2 class="mr-2 h-4 w-4" /> Valider tous les membres </Button>
                    </TooltipTrigger>
                    <TooltipContent>Valide les membres inclus. Les erreurs laissent la campagne en aperçu.</TooltipContent>
                </Tooltip>
                <Tooltip v-if="canManage && campaign.status === 'validated'">
                    <TooltipTrigger as-child>
                        <span>
                            <Button :disabled="!invoicingConfigured" @click="generateInvoices">
                                <FileText class="mr-2 h-4 w-4" /> Générer les factures brouillon
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>Crée une facture brouillon par membre validé. Aucun numéro n’est attribué et rien n’est envoyé.</TooltipContent>
                </Tooltip>
                <Tooltip v-if="canIssueInvoices && campaign.status === 'invoiced'">
                    <TooltipTrigger as-child>
                        <span>
                            <Button :disabled="!invoicingConfigured" @click="issueInvoices">
                                <Send class="mr-2 h-4 w-4" /> Émettre les factures
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>Attribue un numéro, archive le PDF et rend la facture non modifiable. Aucun email n’est envoyé.</TooltipContent>
                </Tooltip>
                <Button v-if="canManage && editable" variant="outline" as-child>
                    <Link :href="route('bo.invoicing.membership-fee-campaigns.edit', campaign.id)"> <Pencil class="mr-2 h-4 w-4" /> Modifier </Link>
                </Button>
                <AppExportMenu
                    v-if="canManage"
                    route-name="bo.invoicing.membership-fee-campaigns.export"
                    :route-params="{ membershipFeeCampaign: campaign.id }"
                    :filters="filters"
                />
                <AppAlertDialog
                    v-if="canRefreshBaseAmounts"
                    title="Recalculer les montants de base ?"
                    description="Les montants seront relus depuis les types de cotisation actuels. Les remises ordinaires sont conservées, les exonérations totales restent totales et les validations seront réinitialisées."
                    cancel-text="Annuler"
                >
                    <template #trigger>
                        <Button variant="outline"> <RefreshCw class="mr-2 h-4 w-4" /> Recalculer les montants de base </Button>
                    </template>
                    <template #action>
                        <Button @click="refreshBaseAmounts">Recalculer</Button>
                    </template>
                </AppAlertDialog>
                <AppAlertDialog v-if="canDeleteCampaign" title="Supprimer la campagne ?" :description="deleteDescription" cancel-text="Annuler">
                    <template #trigger>
                        <Button variant="destructive"> <Trash2 class="mr-2 h-4 w-4" /> Supprimer la campagne </Button>
                    </template>
                    <template #action>
                        <Button variant="destructive" @click="deleteCampaign">Supprimer définitivement</Button>
                    </template>
                </AppAlertDialog>
                <Tooltip v-else-if="canManage">
                    <TooltipTrigger as-child>
                        <span>
                            <Button variant="destructive" disabled> <Trash2 class="mr-2 h-4 w-4" /> Supprimer la campagne </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>Suppression impossible : la campagne contient une facture émise.</TooltipContent>
                </Tooltip>
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
                <AppTooltipLabel label="Membres inclus" description="Membres non exclus de la campagne." class="text-muted-foreground text-sm" />
                <p class="mt-1 text-2xl font-semibold">{{ summary.included }}</p>
                <p class="text-muted-foreground mt-1 text-xs">{{ summary.excluded }} exclu(s) sur {{ summary.total }}</p>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <AppTooltipLabel
                    label="Membres validés"
                    description="Membres prêts pour la génération des factures brouillon."
                    class="text-muted-foreground text-sm"
                />
                <p class="mt-1 text-2xl font-semibold">{{ summary.validated }} / {{ summary.included }}</p>
                <p class="text-muted-foreground mt-1 text-xs">{{ summary.pending }} en attente</p>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <AppTooltipLabel
                    label="Factures générées"
                    description="Factures brouillon ou émises liées à cette campagne."
                    class="text-muted-foreground text-sm"
                />
                <p class="mt-1 text-2xl font-semibold">{{ summary.invoiced }} / {{ summary.included }}</p>
                <p class="text-muted-foreground mt-1 text-xs">{{ summary.issued_invoices }} émise(s) · {{ summary.draft_invoices }} brouillon(s)</p>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <AppTooltipLabel
                    label="Total des cotisations"
                    description="Somme des montants finaux des membres inclus, remises déduites."
                    class="text-muted-foreground text-sm"
                />
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
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2" />
                        <Input id="search" v-model="listFilters.search" class="pl-8" placeholder="Nom ou email" />
                    </div>
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
                            <th class="px-4 py-3 text-left font-medium">
                                <AppTooltipLabel
                                    label="Type de cotisation"
                                    description="Type appliqué lors de l’aperçu. Le montant est figé pour cette campagne."
                                />
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                <div class="flex justify-end">
                                    <AppTooltipLabel label="Cotisation de base" description="Montant standard du type de cotisation." />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                <div class="flex justify-end">
                                    <AppTooltipLabel
                                        label="Remise individuelle"
                                        description="Remise accordée à ce membre, déduite du montant de base."
                                    />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                <div class="flex justify-end">
                                    <AppTooltipLabel label="Montant final" description="Cotisation de base moins la remise individuelle." />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                <AppTooltipLabel
                                    label="Mode d’envoi"
                                    description="Distribution prévue pour la future facture. Aucun envoi immédiat."
                                />
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                <AppTooltipLabel label="Validation" description="État de validation de ce membre dans la campagne." />
                            </th>
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
                                <Tooltip v-if="member.is_full_exemption">
                                    <TooltipTrigger as-child>
                                        <Badge variant="destructive" class="mt-1 cursor-help">Exonération totale</Badge>
                                    </TooltipTrigger>
                                    <TooltipContent>La remise couvre la totalité de la cotisation.</TooltipContent>
                                </Tooltip>
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
                                        <Tooltip v-if="member.status !== 'excluded'">
                                            <TooltipTrigger as-child>
                                                <Button size="sm" variant="outline" @click="openDiscount(member)">Modifier la remise</Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Saisir une remise. Une exonération totale exige un motif.</TooltipContent>
                                        </Tooltip>
                                        <Tooltip v-if="member.status !== 'excluded'">
                                            <TooltipTrigger as-child>
                                                <Button size="sm" variant="outline" @click="openDelivery(member)">Mode d’envoi</Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Choisit email ou postal pour la future facture. Aucun envoi immédiat.</TooltipContent>
                                        </Tooltip>
                                        <Tooltip v-if="member.status === 'pending'">
                                            <TooltipTrigger as-child>
                                                <Button size="sm" variant="outline" @click="validateMember(member)">Valider le membre</Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Rend ce membre éligible à la génération des factures.</TooltipContent>
                                        </Tooltip>
                                        <AppTooltipIconButton
                                            v-if="member.status !== 'excluded'"
                                            label="Exclure ce membre de la campagne"
                                            variant="outline"
                                            @click="excludeMember(member)"
                                        >
                                            <UserMinus class="h-4 w-4" />
                                        </AppTooltipIconButton>
                                        <AppTooltipIconButton
                                            v-if="member.status === 'excluded'"
                                            label="Réintégrer ce membre dans la campagne"
                                            variant="outline"
                                            @click="includeMember(member)"
                                        >
                                            <UserPlus class="h-4 w-4" />
                                        </AppTooltipIconButton>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!members.data?.length">
                            <td colspan="8" class="text-muted-foreground px-4 py-8 text-center">
                                {{
                                    hasActiveFilters
                                        ? 'Aucun membre ne correspond aux filtres.'
                                        : 'Aucun membre. Générez l’aperçu pour calculer les cotisations.'
                                }}
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
                        {{ discountTarget ? formatMoney(discountTarget.base_amount) : '0.00' }} CHF. Une exonération totale couvre toute la
                        cotisation.
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
                        <Button type="submit" :disabled="discountForm.processing">Enregistrer la remise</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deliveryOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mode d’envoi</DialogTitle>
                    <DialogDescription>
                        {{ deliveryTarget ? memberName(deliveryTarget) : '' }}. Ce choix définit la distribution future. Aucun email ni impression
                        n’est déclenché ici.
                    </DialogDescription>
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
                        <Button type="submit" :disabled="deliveryForm.processing">Enregistrer le mode d’envoi</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
