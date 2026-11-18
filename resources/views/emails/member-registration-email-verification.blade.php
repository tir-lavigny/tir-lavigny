<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre code de vérification</title>
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

        .code {
            background-color: hsl(210 40% 96.1%);
            border: 1px solid hsl(214.3 31.8% 91.4%);
            border-radius: 0.5rem;
            padding: 20px;
            margin: 24px 0;
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 0.25em;
            color: hsl(221.2 83.2% 53.3%);
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
            <h1>Vérification de votre email</h1>
            <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 14px; font-weight: 500;">Société de tir de Lavigny</p>
        </div>
        <div class="content">
            <p>Bonjour {{ $registration->form_data['first_name'] ?? 'Inconnu' }},</p>

            <p>Merci pour votre inscription. Pour confirmer votre adresse email, saisissez le code ci-dessous dans le
                formulaire :</p>

            <div class="code">{{ $code }}</div>

            <p>Ce code est valable {{ \App\Services\MemberRegistrationEmailVerificationService::CODE_TTL_MINUTES }}
                minutes et ne peut être utilisé qu'une seule fois.</p>

            <p>Vous n'êtes pas à l'origine de cette demande ? Ignorez simplement cet email.</p>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>

</html>
