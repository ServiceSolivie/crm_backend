@php
    $amount = isset($payment['amount'])
        ? $payment['amount'].(isset($payment['currency']) ? ' '.$payment['currency'] : '')
        : null;

    $details = [
        'Payment ID' => $payment['payment_id'] ?? null,
        'Merchant ID' => $payment['merchant_id'] ?? null,
        'Statut Hyperswitch' => $payment['status'] ?? null,
        'Montant Hyperswitch' => $amount,
        'Connecteur' => $payment['connector'] ?? null,
        'Tentative' => $payment['attempt_count'] ?? null,
        'Référence marchand' => $payment['merchant_order_reference_id'] ?? null,
        'Référence Hyperswitch' => $payment['reference_id'] ?? null,
        'Transaction connecteur' => $payment['connector_transaction_id'] ?? null,
        'Code erreur' => $payment['error_code'] ?? $payment['unified_code'] ?? null,
        'Message erreur' => $payment['error_message'] ?? $payment['unified_message'] ?? null,
        'Créé le' => $payment['created'] ?? null,
        'Mis à jour le' => $payment['modified_at'] ?? $payment['updated'] ?? null,
    ];
@endphp

<p>Paiement Hyperswitch échoué.</p>

@foreach ($details as $label => $value)
    @if ($value !== null && $value !== '')
        <p><strong>{{ $label }} :</strong> {{ $value }}</p>
    @endif
@endforeach

<p><strong>Payload Hyperswitch complet :</strong></p>
<pre><code>{{ $payloadJson }}</code></pre>
