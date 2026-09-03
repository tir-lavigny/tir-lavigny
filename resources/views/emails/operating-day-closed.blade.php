<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clôture journée</title>
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
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
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
            font-weight: 600;
        }

        .content {
            padding: 32px 24px;
            background-color: #ffffff;
        }

        .content p {
            margin: 0 0 16px;
            color: hsl(222.2 84% 4.9%);
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

        .summary strong {
            color: hsl(222.2 47.4% 11.2%);
            font-weight: 600;
        }

        .remark {
            background-color: hsl(38 92% 95%);
            border: 1px solid hsl(32 95% 84%);
            border-radius: 0.5rem;
            padding: 16px;
            margin: 20px 0;
        }

        .remark p {
            margin: 0;
        }

        .remark strong {
            color: hsl(32 95% 40%);
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
            <h1>Clôture de journée</h1>
        </div>
        <div class="content">
            <p>La journée du <strong>{{ $operatingDay->date->format('d.m.Y') }}</strong> (saison
                {{ $operatingDay->season->name }}) a été clôturée.</p>

            <div class="summary">
                <p><strong>Total Espèces :</strong> {{ number_format($operatingDay->total_cash, 2, ',', '.') }} CHF</p>
                <p><strong>Total Twint :</strong> {{ number_format($operatingDay->total_twint, 2, ',', '.') }} CHF</p>
                <p><strong>Solde clôture :</strong> {{ number_format($operatingDay->closing_balance, 2, ',', '.') }} CHF
                </p>
                <p><strong>Tickets :</strong> {{ $operatingDay->tickets_count ?? $operatingDay->tickets->count() }}</p>
            </div>

            <div class="remark">
                <p><strong>Remarque :</strong> {{ $operatingDay->cash_remark }}</p>
            </div>

        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>

</html>
