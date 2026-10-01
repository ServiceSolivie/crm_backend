<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre lien de paiement — {{ $session->reference }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px;">
        <h2 style="margin-top: 0;">Votre lien de paiement</h2>
        <p>Bonjour{{ $clientName ? ' '.$clientName : '' }},</p>
        <p>
            Pour régler votre dossier <strong>{{ $session->reference }}</strong>,
            d'un montant de <strong>{{ $amount }}</strong>, cliquez sur le lien sécurisé ci-dessous.
        </p>

        <p>
            <a href="{{ $session->payment_url }}" style="display: inline-block; margin-top: 16px; padding: 10px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px;">
                Payer {{ $amount }}
            </a>
        </p>

        <p style="margin-top: 24px; font-size: 13px; color: #4b5563;">
            Le paiement est sécurisé par votre banque (3D Secure). Si le bouton ne fonctionne pas,
            copiez ce lien dans votre navigateur :<br>
            <span style="word-break: break-all;">{{ $session->payment_url }}</span>
        </p>

        <p style="margin-top: 32px; font-size: 12px; color: #6b7280;">
            Cet email a été envoyé automatiquement par {{ $companyName }}.
        </p>
    </div>
</body>
</html>
