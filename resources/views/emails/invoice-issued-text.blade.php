Bonjour {{ $invoice->recipient_name }},

Veuillez trouver ci-joint votre facture {{ $invoice->invoice_number }}.

Montant total : {{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}
Échéance : {{ $invoice->due_date?->format('d.m.Y') }}
Référence de paiement : {{ $invoice->payment_reference }}

Le PDF joint contient le détail de la facture et le QR-bill officiel. Conservez la référence de paiement pour tout versement.

Pour toute question concernant cette facture, contactez le comité.

Cordialement,
{{ $invoice->creditor_name }}
{{ $invoice->creditor_address }}
{{ $invoice->creditor_postal_code }} {{ $invoice->creditor_city }} · {{ $invoice->creditor_country }}
