<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('pdfs.partials.style')
</head>
<body>
    <div class="page-top">
        @if ($envelopeWindow === 'right')
            <table class="brand-left">
                <tr>
                    <td class="brand-logo">@inlinedImage(public_path('images/icon-512x512.png'))</td>
                    <td>
                        <div class="brand-name">{{ $invoice->creditor_name }}</div>
                        <div class="brand-meta">
                            {{ $invoice->creditor_address }}<br>
                            {{ $invoice->creditor_postal_code }} {{ $invoice->creditor_city }}<br>
                            {{ $invoice->creditor_country }} · {{ $invoice->creditor_iban }}
                        </div>
                    </td>
                </tr>
            </table>
        @else
            <table class="brand-right">
                <tr>
                    <td class="right">
                        <div class="brand-name">{{ $invoice->creditor_name }}</div>
                        <div class="brand-meta">
                            {{ $invoice->creditor_address }}<br>
                            {{ $invoice->creditor_postal_code }} {{ $invoice->creditor_city }}<br>
                            {{ $invoice->creditor_country }} · {{ $invoice->creditor_iban }}
                        </div>
                    </td>
                    <td class="brand-logo">@inlinedImage(public_path('images/icon-512x512.png'))</td>
                </tr>
            </table>
        @endif

        <div class="address-window window-{{ $envelopeWindow }}">
            <div class="party">
                <strong>{{ $invoice->recipient_name }}</strong><br>
                {{ $invoice->recipient_address }}<br>
                {{ $invoice->recipient_postal_code }} {{ $invoice->recipient_city }}
                @if (strtoupper($invoice->recipient_country) !== 'CH')
                    <br>{{ $invoice->recipient_country }}
                @endif
            </div>
        </div>
    </div>

    <div class="doc-summary">
        <div><span class="type">Rappel de paiement</span><span class="ref">{{ $invoice->invoice_number }}</span></div>
        <div class="meta">
            Facture émise le {{ $invoice->issue_date?->format('d.m.Y') }} · Échéance le {{ $invoice->due_date?->format('d.m.Y') }}
        </div>
    </div>

    <h2>Situation de la facture</h2>
    <p>
        Sauf erreur de notre part, la facture {{ $invoice->invoice_number }} présente encore un solde impayé.
        La facture originale et ses conditions de paiement restent inchangées.
    </p>

    <table class="totals">
        <tr>
            <td>Montant original</td>
            <td class="right">{{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}</td>
        </tr>
        <tr class="grand">
            <td>Solde à payer</td>
            <td class="right">{{ number_format($reminder->outstanding_amount_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}</td>
        </tr>
    </table>

    <div class="reference">
        <div class="eyebrow">Référence de paiement</div>
        <div class="reference-value">{{ $invoice->payment_reference }}</div>
        <div class="small muted" style="margin-top: 1.5mm;">Le QR-bill se trouve sur la page suivante.</div>
    </div>

    <div class="note">
        Merci de régler le solde indiqué en utilisant la référence de paiement ci-dessus.
        Aucun frais de rappel n’est ajouté à cette facture.
    </div>
</body>
</html>
