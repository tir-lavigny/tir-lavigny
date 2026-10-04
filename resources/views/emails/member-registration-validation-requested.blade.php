<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription à valider</title>
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

        .button {
            display: inline-block;
            padding: 10px 16px;
            margin: 24px 0;
            background-color: hsl(221.2 83.2% 53.3%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 0.375rem;
            font-weight: 500;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .button:hover {
            background-color: hsl(217.2 91.2% 59.8%);
        }

        .alert {
            background-color: hsl(210 40% 98%);
            border: 1px solid hsl(214.3 31.8% 91.4%);
            border-radius: 0.5rem;
            padding: 16px;
            margin: 20px 0;
        }

        .alert strong {
            color: hsl(221.2 83.2% 53.3%);
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
            <h1>Inscription à valider</h1>
            <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 14px; font-weight: 500;">Société de tir de Lavigny</p>
        </div>
        <div class="content">
            <p>Une nouvelle inscription a été soumise par <strong>{{ $registration->form_data['first_name'] ?? 'Inconnu' }}
                    {{ $registration->form_data['last_name'] ?? 'Inconnu' }}</strong>. Merci de valider les données avant
                de lancer le traitement.</p>

            <div class="summary">
                <p><strong>Nom :</strong> {{ $registration->form_data['first_name'] ?? 'Inconnu' }}
                    {{ $registration->form_data['last_name'] ?? 'Inconnu' }}</p>
                <p><strong>Email :</strong> {{ $registration->form_data['email'] ?? '-' }}</p>
                <p><strong>Téléphone :</strong> {{ $registration->form_data['tel'] ?? '-' }}</p>
                <p><strong>Adresse :</strong> {{ $registration->form_data['street'] ?? '-' }},
                    {{ $registration->form_data['npa'] ?? '-' }} {{ $registration->form_data['city'] ?? '-' }}</p>
            </div>

            <div class="alert">
                <p><strong>Important :</strong> Vérifiez les données du formulaire avant de lancer le traitement.</p>
            </div>

            <a href="{{ route('bo.member-registrations.show', $registration->id) }}" class="button">Valider
                l'inscription</a>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>

</html>
