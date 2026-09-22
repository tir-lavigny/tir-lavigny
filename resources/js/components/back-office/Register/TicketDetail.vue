<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate, formatMoney, hasPermissionTo } from '@/lib/utils';
import type { Ticket, SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { RotateCcw } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    ticket: Ticket;
    dayIsOpen: boolean;
}>();

const emit = defineEmits<{
    refunded: [];
}>();

const refundForm = useForm({});

const isRefunded = Boolean(props.ticket.deleted_at);
const canRefund = props.dayIsOpen && !isRefunded && hasPermissionTo(user, 'refund ticket');

function voucherLabel(item: NonNullable<Ticket['ticket_items']>[number]) {
    if (!item.voucher) return null;
    if (item.voucher.discount_percent) return `-${item.voucher.discount_percent}%`;
    if (item.voucher.discount_fixed) return `-${formatMoney(item.voucher.discount_fixed)} CHF`;
    return 'Bon';
}

function handleRefund() {
    refundForm.post(route('bo.register.tickets.refund', props.ticket.id), {
        onSuccess: () => {
            toast.success('Ticket remboursé.');
            emit('refunded');
        },
        onError: () => toast.error('Erreur lors du remboursement.'),
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg font-semibold">#{{ ticket.id }}</span>
                <Badge :variant="ticket.payment_method === 'cash' ? 'default' : 'secondary'">
                    {{ ticket.payment_method === 'cash' ? 'Espèces' : 'TWINT' }}
                </Badge>
                <Badge v-if="ticket.stock_location" variant="outline" class="ml-2">
                    {{ ticket.stock_location.name }}
                </Badge>
                <Badge v-if="isRefunded" variant="destructive">Remboursé</Badge>
            </div>
            <span class="text-muted-foreground text-sm">{{ formatDate(ticket.sold_at, 'HH:mm') }}</span>
        </div>

        <p class="text-2xl font-bold" :class="{ 'text-muted-foreground line-through': isRefunded }">{{ formatMoney(ticket.total) }} CHF</p>

        <div v-if="ticket.member" class="bg-muted/50 rounded-lg px-3 py-2 text-sm">
            <span class="text-muted-foreground">Membre :</span>
            <span class="ml-1 font-medium">{{ ticket.member.first_name }} {{ ticket.member.last_name }}</span>
        </div>

        <Separator />

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Produit</TableHead>
                    <TableHead class="text-center">Qté</TableHead>
                    <TableHead class="text-right">Prix unitaire</TableHead>
                    <TableHead class="text-right">Sous-total</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="item in ticket.ticket_items" :key="item.id">
                    <TableCell>
                        {{ item.product?.name ?? '—' }}
                        <Badge v-if="item.voucher" variant="outline" class="ml-2 text-xs">
                            {{ voucherLabel(item) }}
                        </Badge>
                    </TableCell>
                    <TableCell class="text-center">{{ item.quantity }}</TableCell>
                    <TableCell class="text-right">{{ formatMoney(item.unit_price) }} CHF</TableCell>
                    <TableCell class="text-right" :class="{ 'text-muted-foreground line-through': isRefunded }">
                        {{ formatMoney(item.subtotal) }} CHF
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <p v-if="!ticket.ticket_items?.length" class="text-muted-foreground text-sm">Aucun article.</p>

        <template v-if="canRefund">
            <Separator />
            <AlertDialog>
                <AlertDialogTrigger as-child>
                    <Button variant="destructive" :disabled="refundForm.processing"> <RotateCcw class="mr-2 size-4" /> Rembourser </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Rembourser le ticket #{{ ticket.id }} ?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Cette action est irréversible. Le ticket sera supprimé et les totaux de la journée seront recalculés.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction @click="handleRefund" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Rembourser
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </template>
    </div>
</template>
