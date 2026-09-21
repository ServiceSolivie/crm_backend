<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px;">
        <h2 style="margin-top: 0;">Nouveau lead à traiter</h2>
        <p>
            @if ($senderName)
                <strong>{{ $senderName }}</strong> vous a transmis le lead
            @else
                Le lead
            @endif
            <strong>{{ $leadName }}</strong> ({{ $lead->reference }}) pour vérification et traitement.
        </p>

        <p>
            <a href="{{ $leadUrl }}" style="display: inline-block; margin-top: 16px; padding: 10px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px;">
                Voir le lead
            </a>
        </p>

        <p style="margin-top: 32px; font-size: 12px; color: #6b7280;">
            Cet email a été envoyé automatiquement par le CRM Solivie Assur.
        </p>
    </div>
</body>
</html>
