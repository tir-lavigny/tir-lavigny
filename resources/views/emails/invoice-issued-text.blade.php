Bonjour {{ $invoice->recipient_name }},

Veuillez trouver ci-joint votre facture {{ $invoice->invoice_number }}.

Montant total : {{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}
Échéance : {{ $invoice->due_date?->format('d.m.Y') }}
Référence de paiement : {{ $invoice->payment_reference }}

Le QR-bill nécessaire au paiement se trouve dans le PDF joint.

Cordialement,
{{ $invoice->creditor_name }}

Cet email a été envoyé automatiquement, merci de ne pas y répondre.
