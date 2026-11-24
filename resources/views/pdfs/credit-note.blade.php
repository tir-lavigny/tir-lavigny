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
                <div class="brand-name">{{ $creditNote->creditor_name }}</div>
                <div class="brand-meta">
                    {{ $creditNote->creditor_address }}<br>
                    {{ $creditNote->creditor_postal_code }} {{ $creditNote->creditor_city }}<br>
                    {{ $creditNote->creditor_country }}
                </div>
            </td>
            <td class="right">
                <div class="eyebrow">Avoir</div>
                <h1>{{ $creditNote->credit_note_number }}</h1>
                <div class="small muted">Facture {{ $creditNote->invoice->invoice_number }}</div>
            </td>
        </tr>
    </table>

    <table class="doc-meta">
        <tr>
            <td style="width: 58%;">
                <div class="eyebrow">Établi pour</div>
                <div class="party">
                    <strong>{{ $creditNote->recipient_name }}</strong><br>
                    {{ $creditNote->recipient_address }}<br>
                    {{ $creditNote->recipient_postal_code }} {{ $creditNote->recipient_city }}<br>
                    {{ $creditNote->recipient_country }}
                </div>
            </td>
            <td class="right">
                <div class="eyebrow">Émission</div>
                <div>{{ $creditNote->issue_date?->format('d.m.Y') }}</div>
                <div class="small muted" style="margin-top: 3mm;">Devise {{ $creditNote->currency->value }}</div>
            </td>
        </tr>
    </table>

    <h2>Motif</h2>
    <div class="reference">
        <div>{{ $creditNote->reason }}</div>
    </div>

    <h2>Détail de l’avoir</h2>
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
            @foreach ($creditNote->items as $item)
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
            <td class="right">{{ number_format($creditNote->subtotal_cents / 100, 2, '.', ' ') }}</td>
        </tr>
        <tr class="grand">
            <td>Total</td>
            <td class="right">{{ number_format($creditNote->total_cents / 100, 2, '.', ' ') }} {{ $creditNote->currency->value }}</td>
        </tr>
    </table>

    <div class="note">Cet avoir n’est pas un document de paiement.</div>
</body>
</html>
