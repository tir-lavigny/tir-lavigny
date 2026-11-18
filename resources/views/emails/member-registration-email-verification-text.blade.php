Bonjour {{ $registration->form_data['first_name'] ?? 'Inconnu' }},

Merci pour votre inscription à la Société de tir de Lavigny.

Votre code de vérification : {{ $code }}

Saisissez ce code dans le formulaire pour confirmer votre adresse email.
Ce code est valable {{ \App\Services\MemberRegistrationEmailVerificationService::CODE_TTL_MINUTES }} minutes et ne peut être utilisé qu'une seule fois.

Vous n'êtes pas à l'origine de cette demande ? Ignorez simplement cet email.

Cet email a été envoyé automatiquement, merci de ne pas y répondre.
