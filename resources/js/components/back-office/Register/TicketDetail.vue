<script setup lang="ts">
import { computed } from 'vue';
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
import type { Ticket, TicketItem, SharedData } from '@/types';
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

function voucherLabel(voucher: NonNullable<TicketItem['voucher']>): string {
    const percent = voucher.discount_percent ?? 0;
    const fixed = voucher.discount_fixed ?? 0;
    if (percent > 0) return `-${percent}%`;
    if (fixed > 0) return `-${formatMoney(fixed)} CHF`;
    return 'Bon';
}

interface ItemGroup {
    productId: number;
    name: string;
    quantity: number;
    unitPrice: number;
    subtotal: number;
    saved: number;
    vouchers: { id: number; label: string }[];
}

const itemGroups = computed(() => {
    const map = new Map<number, ItemGroup>();

    for (const item of props.ticket.ticket_items ?? []) {
        let group = map.get(item.product_id);
        if (!group) {
            group = {
                productId: item.product_id,
                name: item.product?.name ?? '—',
                quantity: 0,
                unitPrice: Number(item.unit_price),
                subtotal: 0,
                saved: 0,
                vouchers: [],
            };
            map.set(item.product_id, group);
        }
        group.quantity += item.quantity;
        group.subtotal += Number(item.subtotal);
        if (item.voucher) {
            group.vouchers.push({ id: item.voucher.id, label: voucherLabel(item.voucher) ?? 'Bon' });
        }
    }

    return [...map.values()].map((group) => ({
        ...group,
        saved: Math.max(0, Math.round((group.unitPrice * group.quantity - group.subtotal) * 100) / 100),
    }));
});

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
                <TableRow v-for="group in itemGroups" :key="group.productId">
                    <TableCell>
                        {{ group.name }}
                        <Badge v-for="voucher in group.vouchers" :key="voucher.id" variant="outline" class="ml-2 text-xs">
                            Bon {{ voucher.label }}
                        </Badge>
                    </TableCell>
                    <TableCell class="text-center">{{ group.quantity }}</TableCell>
                    <TableCell class="text-right">{{ formatMoney(group.unitPrice) }} CHF</TableCell>
                    <TableCell class="text-right" :class="{ 'text-muted-foreground line-through': isRefunded }">
                        <span v-if="group.saved > 0" class="mr-2 text-xs text-green-600"> (-{{ formatMoney(group.saved) }} CHF) </span>
                        {{ formatMoney(group.subtotal) }} CHF
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
