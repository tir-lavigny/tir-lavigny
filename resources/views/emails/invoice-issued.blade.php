<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: hsl(222.2 84% 4.9%);
            background-color: hsl(210 40% 96.1%);
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .header {
            background: linear-gradient(135deg, hsl(221.2 83.2% 53.3%) 0%, hsl(217.2 91.2% 59.8%) 100%);
            padding: 32px 20px;
            text-align: center;
        }

        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }

        .content {
            padding: 32px 24px;
        }

        .content p {
            margin: 0 0 16px;
        }

        .summary {
            background-color: hsl(210 40% 96.1%);
            padding: 20px;
            margin: 24px 0;
            border-left: 4px solid hsl(221.2 83.2% 53.3%);
            border-radius: 0.375rem;
        }

        .summary p {
            margin: 8px 0;
        }

        .reference {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
            font-size: 16px;
            font-weight: 600;
        }

        .footer {
            margin-top: 24px;
            padding: 20px 24px;
            border-top: 1px solid hsl(214.3 31.8% 91.4%);
            font-size: 13px;
            color: hsl(215.4 16.3% 46.9%);
            text-align: center;
            background-color: hsl(210 40% 98%);
        }

        .footer p {
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Votre facture</h1>
            <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 14px; font-weight: 500;">{{ $invoice->creditor_name }}</p>
        </div>

        <div class="content">
            <p>Bonjour {{ $invoice->recipient_name }},</p>

            <p>Veuillez trouver ci-joint votre facture <strong>{{ $invoice->invoice_number }}</strong>.</p>

            <div class="summary">
                <p><strong>Montant total :</strong> {{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}</p>
                <p><strong>Échéance :</strong> {{ $invoice->due_date?->format('d.m.Y') }}</p>
                <p><strong>Référence de paiement :</strong> <span class="reference">{{ $invoice->payment_reference }}</span></p>
            </div>

            <p>Le QR-bill nécessaire au paiement se trouve dans le PDF joint.</p>

            <p>Cordialement,<br><strong>{{ $invoice->creditor_name }}</strong></p>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>

</html>
