@php
    $details = [
        'Référence CRM' => $session->reference,
        'Payment ID' => $session->hyperswitch_payment_id,
        'Lead ID' => $session->lead_id,
        'Montant' => $session->amount.' '.$session->currency,
        'Statut CRM' => $session->status?->label(),
        'Dernier statut Hyperswitch' => $session->provider_status,
        'Type d’erreur' => class_basename($error),
        'Message d’erreur' => $error->getMessage(),
        'Date' => now()->format('d/m/Y H:i:s'),
    ];
@endphp

<p>Le paiement en attente n’a pas pu être vérifié auprès d’Hyperswitch avant l’envoi d’un nouveau lien : l’agent est bloqué.</p>

@foreach ($details as $label => $value)
    @if ($value !== null && $value !== '')
        <p><strong>{{ $label }} :</strong> {{ $value }}</p>
    @endif
@endforeach
