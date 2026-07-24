{{-- Brand header row + centered document title (template: Devoir de Conseil) --}}
<table class="brand-row">
    <tr>
        <td class="brand-left">
            <img class="brand-logo" src="{{ $logoImg }}" alt="{{ $broker['name'] }}">
            <div class="brand-tagline">Courtier d'assurance — Catégorie B</div>
        </td>
        <td class="brand-right">
            {{ $broker['address'] }}, {{ $broker['postal_code'] }} {{ $broker['city'] }}<br>
            {{ $broker['phone'] }} · {{ $broker['email'] }}<br>
            {{ $broker['website'] }} · {{ $broker['rcs'] }}
        </td>
    </tr>
</table>

<div class="doc-kicker">Document précontractuel</div>
<div class="doc-title">{{ $title }}</div>
<p class="doc-lede">{{ $subtitle }}</p>
