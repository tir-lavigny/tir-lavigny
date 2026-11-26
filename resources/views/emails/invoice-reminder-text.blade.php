Bonjour {{ $invoice->recipient_name }},

Sauf erreur de notre part, la facture {{ $invoice->invoice_number }} présente encore un solde impayé.

Montant original : {{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}
Solde à payer : {{ number_format($reminder->outstanding_amount_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}
Échéance : {{ $invoice->due_date?->format('d.m.Y') }}
Référence de paiement : {{ $invoice->payment_reference }}

La facture originale et ses conditions de paiement restent inchangées. Aucun frais de rappel n’est ajouté.

Le PDF joint contient le rappel avec un QR-bill pour le solde à payer.

Pour toute question concernant cette facture, contactez le comité.

Cordialement,
{{ $invoice->creditor_name }}
{{ $invoice->creditor_address }}
{{ $invoice->creditor_postal_code }} {{ $invoice->creditor_city }} · {{ $invoice->creditor_country }}
