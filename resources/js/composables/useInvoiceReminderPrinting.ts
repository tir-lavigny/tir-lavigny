import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

export function useInvoiceReminderPrinting() {
    const printing = ref(false);

    const printReminder = async (invoiceId: number): Promise<boolean> => {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

        if (!csrfToken) {
            toast.error('Jeton de sécurité introuvable. Rechargez la page.');

            return false;
        }

        printing.value = true;

        try {
            const response = await fetch(route('bo.invoicing.invoices.reminders.print', invoiceId), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            if (!response.ok) {
                const payload = (await response.json().catch(() => null)) as { message?: string } | null;
                toast.error(payload?.message ?? "L'impression du rappel a échoué.");

                return false;
            }

            const blob = await response.blob();
            const blobUrl = URL.createObjectURL(blob);
            const disposition = response.headers.get('content-disposition') ?? '';
            const filename = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? 'rappel.pdf';
            const link = document.createElement('a');

            link.href = blobUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);

            toast.success('Rappel PDF généré.');
            router.reload();

            return true;
        } catch {
            toast.error("L'impression du rappel a échoué.");

            return false;
        } finally {
            printing.value = false;
        }
    };

    return {
        printing,
        printReminder,
    };
}
