{{-- The broker's legal identity and regulatory notice (DVC "broker" section) --}}
<table class="grid">
    <tr>
        <td class="first" style="width: 50%;">
            <div class="field-label">Raison sociale</div>
            <div class="field-value">{{ $broker['name'] }}</div>
        </td>
        <td class="last" style="width: 50%;">
            <div class="field-label">RCS</div>
            <div class="field-value">{{ $broker['rcs'] }}</div>
        </td>
    </tr>
    <tr>
        <td class="first">
            <div class="field-label">Siège social</div>
            <div class="field-value">{{ $broker['address'] }}, {{ $broker['postal_code'] }} {{ $broker['city'] }}</div>
        </td>
        <td class="last">
            <div class="field-label">Site internet</div>
            <div class="field-value">{{ $broker['website'] }}</div>
        </td>
    </tr>
    <tr>
        <td class="first">
            <div class="field-label">Téléphone</div>
            <div class="field-value">{{ $broker['phone'] }}</div>
        </td>
        <td class="last">
            <div class="field-label">E-mail</div>
            <div class="field-value">{{ $broker['email'] }}</div>
        </td>
    </tr>
</table>

<div class="info-box">
    <p>
        {{ $broker['name'] }} exerce son activité de courtage d'assurance conformément à l'article L.520-1 du Code des
        assurances. La liste des compagnies d'assurance avec lesquelles la société travaille en qualité de courtier
        est disponible sur simple demande.
    </p>
    <p>
        En cas de difficulté dans l'application de votre contrat d'assurance, vous pouvez adresser votre réclamation
        par courrier au service réclamations de {{ $broker['name'] }}, à l'adresse du siège social, ou par e-mail à
        <strong>{{ $broker['email'] }}</strong>. Vous recevrez un accusé de réception dans un délai maximum de 48 heures
        et une réponse dans un délai maximum de 7 jours ouvrés.
    </p>
    <p>
        Si la réponse apportée ne vous satisfait pas, vous pouvez demander l'avis d'un médiateur :
        <strong>{{ $mediation['name'] }}</strong> — {{ $mediation['address'] }} ({{ $mediation['website'] }}).
    </p>
    <p class="last">
        {{ $broker['name'] }} est soumise au contrôle de l'<strong>{{ $authority['name'] }}</strong> — {{ $authority['address'] }}.
    </p>
</div>
