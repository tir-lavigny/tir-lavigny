<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur d'inscription</title>
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
            background-color: hsl(0 84.2% 60.2%);
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
            border-left: 4px solid hsl(0 84.2% 60.2%);
            border-radius: 0.375rem;
        }

        .summary p {
            margin: 8px 0;
        }

        .summary strong {
            color: hsl(222.2 47.4% 11.2%);
            font-weight: 600;
        }

        .error-box {
            background-color: hsl(0 84.2% 95%);
            border: 1px solid hsl(0 84.2% 84%);
            border-radius: 0.5rem;
            padding: 16px;
            margin: 20px 0;
        }

        .error-box p {
            margin: 0;
            color: hsl(0 84.2% 30%);
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
            <h1>Erreur d'inscription</h1>
        </div>
        <div class="content">
            <p>Une erreur est survenue lors du traitement de l'inscription de <strong>{{ $registration->form_data['first_name'] ?? 'Inconnu' }} {{ $registration->form_data['last_name'] ?? 'Inconnu' }}</strong>.</p>

            <div class="summary">
                <p><strong>Statut actuel :</strong> {{ $registration->status->value }}</p>
            </div>

            <div class="error-box">
                <p><strong>Erreur :</strong> {{ $registration->error_message }}</p>
            </div>

            <a href="{{ route('bo.member-registrations.show', $registration->id) }}" class="btn">Voir l'inscription</a>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>

</html>
