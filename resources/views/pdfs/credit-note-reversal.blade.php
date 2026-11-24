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
                <div class="brand-name">{{ $reversal->creditor_name }}</div>
                <div class="brand-meta">
                    {{ $reversal->creditor_address }}<br>
                    {{ $reversal->creditor_postal_code }} {{ $reversal->creditor_city }}<br>
                    {{ $reversal->creditor_country }}
                </div>
            </td>
            <td class="right">
                <div class="eyebrow">Avoir d’annulation</div>
                <h1>{{ $reversal->credit_note_reversal_number }}</h1>
                <div class="small muted">Avoir {{ $reversal->creditNote->credit_note_number }}</div>
            </td>
        </tr>
    </table>

    <table class="doc-meta">
        <tr>
            <td style="width: 58%;">
                <div class="eyebrow">Établi pour</div>
                <div class="party">
                    <strong>{{ $reversal->recipient_name }}</strong><br>
                    {{ $reversal->recipient_address }}<br>
                    {{ $reversal->recipient_postal_code }} {{ $reversal->recipient_city }}<br>
                    {{ $reversal->recipient_country }}
                </div>
            </td>
            <td class="right">
                <div class="eyebrow">Émission</div>
                <div>{{ $reversal->issue_date?->format('d.m.Y') }}</div>
                <div class="small muted" style="margin-top: 3mm;">Devise {{ $reversal->currency->value }}</div>
            </td>
        </tr>
    </table>

    <h2>Motif</h2>
    <div class="reference">
        <div>{{ $reversal->reason }}</div>
    </div>

    <h2>Détail de l’annulation</h2>
    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reversal->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format($item->amount_cents / 100, 2, '.', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand">
            <td>Total annulé</td>
            <td class="right">{{ number_format($reversal->total_cents / 100, 2, '.', ' ') }} {{ $reversal->currency->value }}</td>
        </tr>
    </table>

    <div class="note">Cet avoir d’annulation n’est pas un document de paiement.</div>
</body>
</html>
