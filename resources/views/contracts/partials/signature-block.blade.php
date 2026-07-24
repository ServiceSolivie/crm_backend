{{-- Attestation + side-by-side signature boxes (template style) --}}
<div class="sig-section">
    <div class="h1">Attestation et signature</div>
    <p class="body-text">
        Je soussigné(e) reconnais avoir pris connaissance du présent document avant la conclusion du contrat,
        et l'approuve avec la mention «&nbsp;Lu et approuvé&nbsp;».
    </p>

    <table class="sig-table">
        <tr>
            <td class="sig-col">
                <div class="field-label">Le client — Signature</div>
                <div class="sig-box"><span class="sig-hint">Lu et approuvé</span></div>
                <div class="sig-date">Fait le <span class="fill-inline">{{ $generated_at->format('d/m/Y') }}</span></div>
            </td>
            <td class="sig-gap"></td>
            <td class="sig-col">
                <div class="field-label">Le courtier conseil — {{ $broker['name'] }}</div>
                <div class="sig-box"><img class="sig-img" src="{{ $signatureImg }}" alt=""></div>
                <div class="sig-date">Fait le <span class="fill-inline">{{ $generated_at->format('d/m/Y') }}</span></div>
            </td>
        </tr>
    </table>
</div>

<div class="legal-footer">
    {{ $broker['name'] }} — Courtier d'assurance catégorie B — {{ $broker['rcs'] }} — {{ $broker['address'] }},
    {{ $broker['postal_code'] }} {{ $broker['city'] }}, {{ $broker['country'] }}<br>
    Sous le contrôle de l'{{ $authority['name'] }}, {{ $authority['address'] }} — {{ $broker['website'] }}
</div>
