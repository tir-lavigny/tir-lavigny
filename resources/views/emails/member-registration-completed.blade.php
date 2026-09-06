<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription complétée</title>
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
            background-color: hsl(221.2 83.2% 53.3%);
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

        .btn {
            display: inline-block;
            background-color: hsl(221.2 83.2% 53.3%);
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 0.375rem;
            font-weight: 600;
            margin: 16px 0;
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
            <h1>Inscription complétée</h1>
        </div>
        <div class="content">
            <p>L'inscription de <strong>{{ $registration->form_data['first_name'] }}
                    {{ $registration->form_data['last_name'] }}</strong> a été traitée avec succès.</p>

            <div class="summary">
                <p><strong>Nom :</strong> {{ $registration->form_data['first_name'] }}
                    {{ $registration->form_data['last_name'] }}</p>
                <p><strong>Email :</strong> {{ $registration->form_data['email'] }}</p>
                <p><strong>DAV créé :</strong> Oui (UID: {{ $registration->dav_uid }})</p>
                <p><strong>Membre créé :</strong> Oui</p>
                <p><strong>Bons de base :</strong>
                    {{ $registration->member? $registration->member->vouchers()->where('season_id', optional($registration->member->vouchers()->first()?->season)->id)->count(): 0 }}
                </p>
            </div>

            <p>Il ne reste plus qu'à l'inscrire dans le SAT</p>

            <a href="{{ route('bo.member-registrations.show', $registration->id) }}" class="btn">Voir le détail de
                l'inscription</a>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>

</html>
