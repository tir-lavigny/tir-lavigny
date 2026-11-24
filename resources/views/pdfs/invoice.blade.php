<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 18mm 18mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #111827; font-size: 10pt; }
        h1 { font-size: 20pt; margin: 0 0 2mm; }
        h2 { font-size: 11pt; margin: 7mm 0 2mm; }
        .muted { color: #6b7280; }
        .small { font-size: 8pt; }
        .header { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .header .right { text-align: right; }
        .box { border: 0.3mm solid #d1d5db; padding: 3mm; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 5mm; }
        .lines th { text-align: left; border-bottom: 0.3mm solid #111827; padding: 2mm 1mm; font-size: 8pt; }
        .lines td { border-bottom: 0.2mm solid #e5e7eb; padding: 2mm 1mm; vertical-align: top; }
        .right { text-align: right; }
        .totals { width: 70mm; margin-left: auto; margin-top: 4mm; border-collapse: collapse; }
        .totals td { padding: 1mm 0; }
        .totals .grand td { border-top: 0.4mm solid #111827; font-weight: bold; padding-top: 2mm; }
        .reference { margin-top: 6mm; padding: 3mm; background: #f3f4f6; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Facture</h1>
                <div>{{ $invoice->invoice_number }}</div>
            </td>
            <td class="right">
                <div><strong>{{ $invoice->creditor_name }}</strong></div>
                <div>{{ $invoice->creditor_address }}</div>
                <div>{{ $invoice->creditor_postal_code }} {{ $invoice->creditor_city }}</div>
                <div>{{ $invoice->creditor_country }}</div>
                <div class="small">{{ $invoice->creditor_iban }}</div>
            </td>
        </tr>
    </table>

    <table class="header" style="margin-top: 8mm;">
        <tr>
            <td>
                <div class="muted small">Facturé à</div>
                <div><strong>{{ $invoice->recipient_name }}</strong></div>
                <div>{{ $invoice->recipient_address }}</div>
                <div>{{ $invoice->recipient_postal_code }} {{ $invoice->recipient_city }}</div>
                <div>{{ $invoice->recipient_country }}</div>
            </td>
            <td class="right">
                <div><span class="muted">Émission :</span> {{ $invoice->issue_date?->format('d.m.Y') }}</div>
                <div><span class="muted">Échéance :</span> {{ $invoice->due_date?->format('d.m.Y') }}</div>
                <div><span class="muted">Devise :</span> {{ $invoice->currency->value }}</div>
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
        <div class="small muted">Référence de paiement</div>
        <div>{{ $invoice->payment_reference }}</div>
        <div class="small" style="margin-top: 2mm;">Le QR-bill se trouve sur la page suivante.</div>
    </div>

    <p class="small muted" style="margin-top: 8mm;">
        Prière de payer le montant total jusqu’au {{ $invoice->due_date?->format('d.m.Y') }} en utilisant la référence de paiement ci-dessus.
    </p>
</body>
</html>
