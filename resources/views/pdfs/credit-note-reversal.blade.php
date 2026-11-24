<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #111827; font-size: 10pt; }
        h1 { font-size: 20pt; margin: 0 0 2mm; }
        h2 { font-size: 11pt; margin: 7mm 0 2mm; }
        .muted { color: #6b7280; }
        .small { font-size: 8pt; }
        .header { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 5mm; }
        .lines th { text-align: left; border-bottom: 0.3mm solid #111827; padding: 2mm 1mm; font-size: 8pt; }
        .lines td { border-bottom: 0.2mm solid #e5e7eb; padding: 2mm 1mm; vertical-align: top; }
        .totals { width: 70mm; margin-left: auto; margin-top: 4mm; border-collapse: collapse; }
        .totals td { padding: 1mm 0; }
        .totals .grand td { border-top: 0.4mm solid #111827; font-weight: bold; padding-top: 2mm; }
        .reason { margin-top: 5mm; border: 0.3mm solid #d1d5db; padding: 3mm; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Avoir d’annulation</h1>
                <div>{{ $reversal->credit_note_reversal_number }}</div>
                <div class="muted small">Avoir annulé : {{ $reversal->creditNote->credit_note_number }}</div>
            </td>
            <td class="right">
                <div><strong>{{ $reversal->creditor_name }}</strong></div>
                <div>{{ $reversal->creditor_address }}</div>
                <div>{{ $reversal->creditor_postal_code }} {{ $reversal->creditor_city }}</div>
                <div>{{ $reversal->creditor_country }}</div>
            </td>
        </tr>
    </table>

    <table class="header" style="margin-top: 8mm;">
        <tr>
            <td>
                <div class="muted small">Établi pour</div>
                <div><strong>{{ $reversal->recipient_name }}</strong></div>
                <div>{{ $reversal->recipient_address }}</div>
                <div>{{ $reversal->recipient_postal_code }} {{ $reversal->recipient_city }}</div>
                <div>{{ $reversal->recipient_country }}</div>
            </td>
            <td class="right">
                <div><span class="muted">Émission :</span> {{ $reversal->issue_date?->format('d.m.Y') }}</div>
                <div><span class="muted">Devise :</span> {{ $reversal->currency->value }}</div>
            </td>
        </tr>
    </table>

    <div class="reason">
        <div class="muted small">Motif</div>
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

    <p class="small muted" style="margin-top: 8mm;">Cet avoir d’annulation n’est pas un document de paiement.</p>
</body>
</html>
