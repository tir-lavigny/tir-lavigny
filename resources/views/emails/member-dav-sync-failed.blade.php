<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Synchronisation DAV échouée</title>
</head>

<body style="font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.6; color: #111827; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #ffffff; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);">
        <div style="background: #b91c1c; padding: 24px 20px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Synchronisation DAV échouée</h1>
        </div>
        <div style="padding: 24px;">
            <p>Le membre <strong>{{ $member->first_name }} {{ $member->last_name }}</strong> a été enregistré dans l'application, mais la synchronisation avec le carnet de contacts Infomaniak a échoué.</p>

            <div style="background: #f3f4f6; padding: 16px; margin: 20px 0; border-left: 4px solid #b91c1c; border-radius: 0.375rem;">
                <p style="margin: 0 0 8px;"><strong>Email :</strong> {{ $member->email_masked ?? '—' }}</p>
                <p style="margin: 0 0 8px;"><strong>Erreur :</strong> {{ $error }}</p>
            </div>

            <p>Vous pouvez relancer la synchronisation depuis la fiche du membre ou depuis l'inscription.</p>

            <a href="{{ route('bo.member-registrations.show', $registration) }}" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 0.375rem; font-weight: 500;">Voir l'inscription</a>
        </div>
        <div style="margin-top: 24px; padding: 16px 24px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280; text-align: center;">
            <p style="margin: 0;">Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>

</html>
