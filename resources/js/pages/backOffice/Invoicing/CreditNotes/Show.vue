<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, hasAnyPermission } from '@/lib/utils';
import type { BreadcrumbItem, CreditNote, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{ creditNote: CreditNote; availableCents: number }>();

const page = usePage<SharedData>();
const user = page.props.auth.user;
const canCreate = computed(() => hasAnyPermission(user, ['create invoices', 'manage invoicing']));
const canIssue = computed(() => hasAnyPermission(user, ['issue invoices', 'manage invoicing']));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avoirs', href: route('bo.invoicing.credit-notes.index') },
    {
        title: props.creditNote.credit_note_number ?? `Brouillon #${props.creditNote.id}`,
        href: route('bo.invoicing.credit-notes.show', props.creditNote.id),
    },
];

const issueCreditNote = () => {
    router.post(
        route('bo.invoicing.credit-notes.issue', props.creditNote.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Avoir émis.'),
            onError: () => toast.error("Erreur lors de l'émission de l'avoir."),
        },
    );
};

const deleteCreditNote = () => {
    router.delete(route('bo.invoicing.credit-notes.destroy', props.creditNote.id), {
        onSuccess: () => toast.success('Avoir brouillon supprimé.'),
        onError: () => toast.error("Erreur lors de la suppression de l'avoir."),
    });
};

const statusLabel = computed(() => {
    return {
        draft: 'Brouillon',
        issued: 'Émis',
        partially_used: 'Partiellement utilisé',
        fully_used: 'Entièrement utilisé',
        partially_reversed: 'Partiellement annulé',
        reversed: 'Annulé',
    }[props.creditNote.status];
});
</script>

<template>
    <Head :title="creditNote.credit_note_number ?? `Brouillon #${creditNote.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-col gap-2">
                <H1>{{ creditNote.credit_note_number ?? `Brouillon #${creditNote.id}` }}</H1>
                <div class="text-muted-foreground flex items-center gap-2 text-sm">
                    <Badge variant="secondary">{{ statusLabel }}</Badge>
                    <Link class="underline" :href="route('bo.invoicing.invoices.show', creditNote.invoice_id)">
                        Facture {{ creditNote.invoice?.invoice_number }}
                    </Link>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="creditNote.status === 'draft' && canCreate" as-child variant="outline">
                    <Link :href="route('bo.invoicing.credit-notes.edit', creditNote.id)"> <Pencil class="mr-2 h-4 w-4" /> Modifier </Link>
                </Button>
                <AppAlertDialog
                    v-if="creditNote.status === 'draft' && canIssue"
                    title="Émettre cet avoir ?"
                    description="Un numéro définitif sera attribué. L'avoir ne pourra plus être modifié."
                    cancel-text="Annuler"
                >
                    <template #trigger>
                        <Button><CheckCircle2 class="mr-2 h-4 w-4" /> Émettre</Button>
                    </template>
                    <template #action>
                        <Button @click="issueCreditNote">Émettre</Button>
                    </template>
                </AppAlertDialog>
                <AppAlertDialog
                    v-if="creditNote.status === 'draft' && canCreate"
                    title="Supprimer ce brouillon ?"
                    description="L'avoir brouillon sera supprimé définitivement."
                    cancel-text="Annuler"
                >
                    <template #trigger>
                        <Button variant="destructive"><Trash2 class="mr-2 h-4 w-4" /> Supprimer</Button>
                    </template>
                    <template #action>
                        <Button variant="destructive" @click="deleteCreditNote">Supprimer</Button>
                    </template>
                </AppAlertDialog>
            </div>
        </div>

        <div class="mx-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4 lg:col-span-2">
                <H3>Client</H3>
                <div class="mt-4 text-sm">
                    <div class="font-medium">{{ creditNote.recipient_name }}</div>
                    <div>{{ creditNote.recipient_address }}</div>
                    <div>{{ creditNote.recipient_postal_code }} {{ creditNote.recipient_city }}</div>
                    <div>{{ creditNote.recipient_country }}</div>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <H3>Montants</H3>
                <div class="mt-4 grid gap-2 text-sm">
                    <div class="flex justify-between">
                        <span>Total</span><span class="font-mono">{{ formatMoney(creditNote.total) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Disponible</span><span class="font-mono">{{ formatMoney(availableCents / 100) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Émis le</span><span>{{ creditNote.issued_at ? formatDate(creditNote.issued_at, 'DD.MM.YYYY') : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Motif</H3>
            <p class="mt-2 text-sm">{{ creditNote.reason }}</p>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Lignes</H3>
            <div class="mt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Quantité</TableHead>
                            <TableHead class="text-right">Prix unitaire</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in creditNote.items" :key="item.id">
                            <TableCell>{{ item.description }}</TableCell>
                            <TableCell class="text-right">{{ item.quantity }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatMoney(item.unit_price) }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatMoney(item.total) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <div v-if="creditNote.applications?.length" class="border-sidebar-border/70 dark:border-sidebar-border mx-4 mt-4 rounded-xl border p-4">
            <H3>Imputations</H3>
            <Table class="mt-4">
                <TableHeader>
                    <TableRow>
                        <TableHead>Facture</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead class="text-right">Montant</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="application in creditNote.applications" :key="application.id">
                        <TableCell>
                            <Link class="underline" :href="route('bo.invoicing.invoices.show', application.invoice_id)">
                                {{ application.invoice?.invoice_number ?? `#${application.invoice_id}` }}
                            </Link>
                        </TableCell>
                        <TableCell>{{ formatDate(application.applied_at, 'DD.MM.YYYY') }}</TableCell>
                        <TableCell class="text-right font-mono">{{ formatMoney(application.amount) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
