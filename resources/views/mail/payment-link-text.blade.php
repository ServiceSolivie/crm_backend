Bonjour{{ $clientName ? ' '.$clientName : '' }},

Pour régler votre dossier {{ $session->reference }}, d'un montant de {{ $amount }}, ouvrez le lien sécurisé ci-dessous :

{{ $session->payment_url }}

Le paiement est sécurisé par votre banque (3D Secure).

Cet e-mail a été envoyé automatiquement par {{ $companyName }}.
