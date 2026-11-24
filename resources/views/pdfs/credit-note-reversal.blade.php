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
                        <div class="brand-name">{{ $reversal->creditor_name }}</div>
                        <div class="brand-meta">
                            {{ $reversal->creditor_address }}<br>
                            {{ $reversal->creditor_postal_code }} {{ $reversal->creditor_city }}<br>
                            {{ $reversal->creditor_country }}
                        </div>
                    </td>
                </tr>
            </table>
        @else
            <table class="brand-right">
                <tr>
                    <td class="right">
                        <div class="brand-name">{{ $reversal->creditor_name }}</div>
                        <div class="brand-meta">
                            {{ $reversal->creditor_address }}<br>
                            {{ $reversal->creditor_postal_code }} {{ $reversal->creditor_city }}<br>
                            {{ $reversal->creditor_country }}
                        </div>
                    </td>
                    <td class="brand-logo">@inlinedImage(public_path('images/icon-512x512.png'))</td>
                </tr>
            </table>
        @endif

        <div class="address-window window-{{ $envelopeWindow }}">
            <div class="party">
                <strong>{{ $reversal->recipient_name }}</strong><br>
                {{ $reversal->recipient_address }}<br>
                {{ $reversal->recipient_postal_code }} {{ $reversal->recipient_city }}
                @if (strtoupper($reversal->recipient_country) !== 'CH')
                    <br>{{ $reversal->recipient_country }}
                @endif
            </div>
        </div>

    </div>

    <div class="doc-summary">
        <div><span class="type">Avoir d’annulation</span><span class="ref">{{ $reversal->credit_note_reversal_number }}</span></div>
        <div class="meta">
            Avoir {{ $reversal->creditNote->credit_note_number }} · Émis le {{ $reversal->issue_date?->format('d.m.Y') }} · {{ $reversal->currency->value }}
        </div>
    </div>

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
