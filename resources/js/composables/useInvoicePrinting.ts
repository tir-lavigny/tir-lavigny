import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

export function useInvoicePrinting() {
    const printing = ref(false);

    const requestPrint = async (url: string, invoiceIds: number[]): Promise<boolean> => {
        if (invoiceIds.length === 0) {
            toast.error('Sélectionnez au moins une facture.');

            return false;
        }

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

        if (!csrfToken) {
            toast.error('Jeton de sécurité introuvable. Rechargez la page.');

            return false;
        }

        printing.value = true;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ invoice_ids: invoiceIds }),
            });

            if (!response.ok) {
                const payload = (await response.json().catch(() => null)) as { message?: string } | null;
                toast.error(payload?.message ?? "L'impression a échoué.");

                return false;
            }

            const blob = await response.blob();
            const blobUrl = URL.createObjectURL(blob);
            const disposition = response.headers.get('content-disposition') ?? '';
            const filename = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? 'impression-postale.pdf';
            const link = document.createElement('a');

            link.href = blobUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);

            toast.success('PDF d’impression généré.');
            router.reload();

            return true;
        } catch {
            toast.error("L'impression a échoué.");

            return false;
        } finally {
            printing.value = false;
        }
    };

    return {
        printing,
        printInvoices: (invoiceIds: number[]): Promise<boolean> => requestPrint(route('bo.invoicing.deliveries.postal.print'), invoiceIds),
        printInvoice: (invoiceId: number): Promise<boolean> => requestPrint(route('bo.invoicing.invoices.print', invoiceId), [invoiceId]),
    };
}
