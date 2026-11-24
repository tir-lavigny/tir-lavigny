<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('pdfs.partials.style')
</head>
<body>
    <table class="brand">
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
            <td class="right">
                <div class="eyebrow">Facture</div>
                <h1>{{ $invoice->invoice_number }}</h1>
                <div class="small muted">Émise le {{ $invoice->issue_date?->format('d.m.Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="doc-meta">
        <tr>
            <td style="width: 58%;">
                <div class="eyebrow">Facturé à</div>
                <div class="party">
                    <strong>{{ $invoice->recipient_name }}</strong><br>
                    {{ $invoice->recipient_address }}<br>
                    {{ $invoice->recipient_postal_code }} {{ $invoice->recipient_city }}<br>
                    {{ $invoice->recipient_country }}
                </div>
            </td>
            <td class="right">
                <div class="eyebrow">Échéance</div>
                <div>{{ $invoice->due_date?->format('d.m.Y') }}</div>
                <div class="small muted" style="margin-top: 3mm;">Devise {{ $invoice->currency->value }}</div>
            </td>
        </tr>
    </table>

    <h2>Détail de la facture</h2>
    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Quantité</th>
                <th class="right">Prix unitaire</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
                    <td class="right">{{ number_format($item->unit_price_cents / 100, 2, '.', ' ') }}</td>
                    <td class="right">{{ number_format($item->total_cents / 100, 2, '.', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Sous-total</td>
            <td class="right">{{ number_format($invoice->subtotal_cents / 100, 2, '.', ' ') }}</td>
        </tr>
        <tr class="grand">
            <td>Total</td>
            <td class="right">{{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}</td>
        </tr>
    </table>

    <div class="reference">
        <div class="eyebrow">Référence de paiement</div>
        <div class="reference-value">{{ $invoice->payment_reference }}</div>
        <div class="small muted" style="margin-top: 1.5mm;">Le QR-bill se trouve sur la page suivante.</div>
    </div>

    <div class="note">
        Prière de payer le montant total jusqu’au {{ $invoice->due_date?->format('d.m.Y') }} en utilisant la référence de paiement ci-dessus.
    </div>
</body>
</html>
