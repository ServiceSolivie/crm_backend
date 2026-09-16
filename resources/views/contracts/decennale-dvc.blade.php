<?php
/** @var array $data */
$v = function (string $key, string $default = '') use ($data) {
    $value = $data[$key] ?? null;

    return ($value !== null && $value !== '' && $value !== false) ? (string) $value : $default;
};

$d = function (string $key, string $default = '') use ($data) {
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        return $default;
    }
    try {
        return \Carbon\Carbon::parse($value)->format('d/m/Y');
    } catch (\Throwable) {
        return (string) $value;
    }
};

$money = function (string $key, string $default = '') use ($data) {
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        return $default;
    }

    return number_format((float) $value, 2, ',', ' ').' €';
};

$included = fn (string $key) => filter_var($data["{$key}_included"] ?? false, FILTER_VALIDATE_BOOLEAN);

$garanties = [
    'g_rc_decennale' => 'Responsabilité civile décennale (RCD)',
    'g_rc_pro' => 'Responsabilité civile professionnelle',
    'g_rc_exploitation' => "Responsabilité civile d'exploitation",
    'g_bon_fonctionnement' => 'Garantie de bon fonctionnement (2 ans)',
    'g_apres_travaux' => 'Garantie après travaux',
    'g_defense' => 'Défense pénale et recours',
    'g_gap' => 'Garantie accident de la vie professionnelle (GAP)',
    'g_dommages_ouvrage' => "Dommages-ouvrage (si maître d'ouvrage)",
];

$fontDir = str_replace('\\', '/', storage_path('fonts'));
$logoImg = str_replace('\\', '/', public_path('images/contracts/moov-assur-logo.svg'));
$signatureImg = str_replace('\\', '/', public_path('images/contracts/moov-assur-signature.png'));
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @font-face {
        font-family: 'Lato';
        font-style: normal;
        font-weight: 400;
        src: url('{{ $fontDir }}/Lato-Regular.ttf') format('truetype');
    }
    @font-face {
        font-family: 'Lato';
        font-style: normal;
        font-weight: 700;
        src: url('{{ $fontDir }}/Lato-Bold.ttf') format('truetype');
    }

    /* 3,8 cm de marge sur les quatre côtés (annotations + reliure).
       NB : ne jamais remettre `html` (ni `*`) dans le reset ci-dessous —
       dompdf mappe la marge de l'élément html sur la boîte de page,
       ce qui annulerait les marges @page. */
    @page {
        margin: 2.8cm;
        margin-top : 1.8cm;
        margin-bottom : 1.8cm;
    }

    body, div, p, table, tr, td, th, ul, li, span {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Lato', sans-serif;
        font-size: 9pt;
        color: #3d4a5c;
        line-height: 1.6;
    }

    /* ── Fixed footer ─────────────────────────────── */
    .doc-footer { position: fixed; bottom: -62px; left: 0; right: 0; }
    .footer-table { width: 100%; border-top: 0.75px solid #d3dce6; padding-top: 6px; border-collapse: collapse; }
    .footer-left { font-size: 6.6pt; color: #9aa8b8; text-align: left; }
    .footer-right { font-size: 6.6pt; color: #9aa8b8; text-align: right; white-space: nowrap; }
    .pagenum:before { content: counter(page); }

    /* ── En-tête de marque ────────────────────────── */
    .brand-row { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    .brand-left { vertical-align: top; }
    .brand-logo { height: 40px; display: block; }
    .brand-tagline { font-size: 7.5pt; color: #5b6b7d; margin-top: 5px; }
    .brand-right { vertical-align: top; text-align: right; font-size: 7pt; color: #7a8797; line-height: 1.7; }

    .doc-kicker {
        text-align: center;
        font-size: 7.5pt;
        font-weight: 700;
        color: #2779c7;
        text-transform: uppercase;
        letter-spacing: 2.5px;
        margin-bottom: 6px;
    }
    .doc-title {
        text-align: center;
        font-size: 22pt;
        font-weight: 700;
        color: #16202c;
        margin-bottom: 12px;
    }
    .doc-lede {
        text-align: center;
        font-size: 9pt;
        color: #5b6b7d;
        width: 82%;
        margin: 0 auto 24px auto;
        line-height: 1.7;
    }

    /* ── Titres de section ────────────────────────── */
    .h1 {
        font-size: 13.5pt;
        font-weight: 700;
        color: #16202c;
        margin: 26px 0 12px 0;
        text-decoration: underline;
    }
    .h1 .num {
        font-size: 8pt;
        font-weight: 700;
        color: #2b6cb5;
        vertical-align: 3px;
        margin-right: 7px;
        letter-spacing: 1px;
    }
    .h2 {
        font-size: 10.5pt;
        font-weight: 700;
        color: #16202c;
        margin: 20px 0 8px 0;
    }

    p.body-text { font-size: 9pt; color: #3d4a5c; text-align: justify; margin-bottom: 9px; line-height: 1.65; }
    p.body-text strong { color: #16202c; }

    /* ── Grille label / valeur ────────────────────── */
    .grid { width: 100%; border-collapse: collapse; }
    .grid td { width: 50%; vertical-align: bottom; padding: 0 0 16px 0; }
    .grid td.gl { padding-right: 14px; }
    .grid td.gr { padding-left: 14px; }
    .grid td.full { width: 100%; }

    .field-label {
        font-size: 6.8pt;
        font-weight: 700;
        color: #2b6cb5;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }
    .field-value {
        font-size: 9.5pt;
        color: #16202c;
        border-bottom: 0.75px solid #b9c6d4;
        padding: 1px 1px 4px 1px;
        min-height: 13pt;
    }

    .fill-inline {
        display: inline-block;
        min-width: 70px;
        border-bottom: 0.75px solid #b9c6d4;
        color: #16202c;
        padding: 0 4px 1px 4px;
        text-align: center;
    }

    /* ── Encadré informatif ───────────────────────── */
    .info-box {
        background: #e3f0fc;
        border: 0.75px solid #97c4ec;
        border-radius: 8px;
        padding: 16px 18px;
        margin: 14px 0 18px 0;
        page-break-inside: avoid;
    }
    .info-box p { font-size: 8.6pt; color: #2c3a4c; text-align: justify; margin-bottom: 9px; line-height: 1.65; }
    .info-box p.last { margin-bottom: 0; }
    .info-box strong { color: #16202c; }

    /* ── Tableau des garanties ────────────────────── */
    .gtable { width: 100%; border-collapse: collapse; margin: 6px 0 4px 0; }
    .gtable th {
        background: #ddeefc;
        border-bottom: 1.2px solid #2b6cb5;
        font-size: 7pt;
        font-weight: 700;
        color: #2b6cb5;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-align: left;
        padding: 8px 10px;
    }
    .gtable td { padding: 7.5px 10px; font-size: 9pt; color: #2c3a4c; border-bottom: 0.6px solid #dbe5ef; }
    .gtable tr.alt td { background: #eff7fe; }

    .cb {
        display: inline-block;
        width: 10px;
        height: 10px;
        border: 1.1px solid #6aa4d8;
        border-radius: 2px;
        background: #ffffff;
    }
    .cb-on { background: #2b6cb5; border-color: #2b6cb5; }

    /* ── Liste de documents ────────────────────────── */
    .doc-list { width: 100%; border-collapse: collapse; }
    .doc-list td { padding: 4.5px 0; font-size: 9pt; color: #2c3a4c; vertical-align: middle; }
    .doc-list td.dot { width: 16px; }
    .doc-list td.dot span {
        display: inline-block;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #2b6cb5;
    }

    /* ── Signatures ───────────────────────────────── */
    .sig-section { margin-top: 8px; }
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 10px; page-break-inside: avoid; }
    .sig-col { width: 46%; vertical-align: top; }
    .sig-gap { width: 8%; }
    .sig-box {
        border: 0.9px solid #b9c6d4;
        border-radius: 8px;
        height: 78px;
        margin-top: 4px;
        padding: 6px 8px;
        vertical-align: bottom;
        text-align: center;
    }
    .sig-img { height: 56px; margin-top: 8px; }
    .sig-hint { font-size: 7pt; color: #9aa8b8; }
    .sig-date { font-size: 8.5pt; color: #3d4a5c; margin-top: 8px; }

    .legal-footer {
        border-top: 0.75px solid #d3dce6;
        margin-top: 26px;
        padding-top: 10px;
        font-size: 6.9pt;
        color: #7a8797;
        line-height: 1.7;
    }

    .page-break { page-break-before: always; }
    .keep { page-break-inside: avoid; }
    .spacer-lg { height: 22px; }
</style>
</head>
<body>

@include('contracts.partials.footer')

@include('contracts.partials.header', [
    'title' => 'Devoir de Conseil',
    'subtitle' => "Étude personnalisée des besoins de votre entreprise, réalisée conformément aux articles L.520-1 et R.520-1 du Code des assurances, afin de vous proposer la couverture décennale la mieux adaptée à votre activité.",
])

{{-- I — Le courtier conseil --}}
<div class="h1"><span class="num">I</span>Le courtier conseil</div>
<p class="body-text">
    Ce contrat est commercialisé par la société <strong>{{ $broker['name'] }}</strong>, courtier d'assurance de
    catégorie B, immatriculée sous le numéro {{ $broker['rcs'] }}.
    Le siège social de la société est situé {{ $broker['address'] }}, {{ $broker['postal_code'] }} {{ $broker['city'] }}, {{ $broker['country'] }}.
</p>

<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<table class="grid">
    <tr>
        <td class="gl">
            <div class="field-label">Votre conseiller</div>
            <div class="field-value">{{ $v('agent_name') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Ligne directe</div>
            <div class="field-value">{{ $v('agent_phone') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Adresse e-mail</div>
            <div class="field-value">{{ $v('agent_email') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Date de l'entretien</div>
            <div class="field-value">{{ $d('interview_date', $generated_at->format('d/m/Y')) }}&nbsp;</div>
        </td>
    </tr>
</table>

{{-- II — Informations légales --}}
<div class="h1"><span class="num">II</span>Informations légales</div>
<table class="grid">
    <tr>
        <td class="gl">
            <div class="field-label">Raison sociale</div>
            <div class="field-value">{{ $broker['name'] }}</div>
        </td>
        <td class="gr">
            <div class="field-label">RCS</div>
            <div class="field-value">{{ $broker['rcs'] }}</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Siège social</div>
            <div class="field-value">{{ $broker['address'] }}, {{ $broker['postal_code'] }} {{ $broker['city'] }}</div>
        </td>
        <td class="gr">
            <div class="field-label">Site internet</div>
            <div class="field-value">{{ $broker['website'] }}</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Téléphone</div>
            <div class="field-value">{{ $broker['phone'] }}</div>
        </td>
        <td class="gr">
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

{{-- III — Remarques importantes --}}

<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="h1"><span class="num">III</span>Remarques importantes</div>
<p class="body-text">
    Nous attirons votre attention sur le fait que la fourniture d'une information complète et sincère sur l'activité
    de votre entreprise est une condition indispensable à la délivrance d'un conseil adapté. À défaut de réponse à
    une question, ou en cas de réponse incomplète ou erronée, la fiabilité et la pertinence de cette étude — et donc
    des solutions qui vous seront proposées — pourraient être compromises.
</p>
<p class="body-text">
    Vous reconnaissez avoir pris connaissance du contenu du présent document préalablement à l'adhésion au contrat
    d'assurance proposé, en avoir conservé un exemplaire, et avoir reçu une information détaillée sur l'étendue,
    la définition des risques et des garanties proposées.
</p>
<p class="body-text">
    Aussi précis que soient les informations et les conseils qui vous ont été donnés, il est très important que vous
    lisiez attentivement les notices de votre précontrat d'assurance qui vous seront remises au moment de votre
    adhésion. Ces notices constituent le document juridique contractuel exprimant les droits et obligations de
    l'assuré et de l'assureur.
</p>
<p class="body-text">
    Nous insistons sur l'importance de la précision et de la sincérité des réponses apportées dans la demande
    d'adhésion, notamment sur la nature exacte de l'activité exercée. <strong>Une fausse déclaration intentionnelle
    entraîne la nullité du contrat et la déchéance de vos garanties.</strong>
</p>
<p class="body-text">
    Conformément à la loi Informatique et Libertés du 6 janvier 1978 modifiée en 2004, vous bénéficiez d'un droit
    d'accès, de rectification et d'opposition aux informations qui vous concernent, que vous pouvez exercer en vous
    adressant au siège social de la société {{ $broker['name'] }}. Les données à caractère personnel recueillies sont
    nécessaires à la conclusion du présent contrat et pourront être utilisées par l'assureur et ses partenaires.
</p>

{{-- ══ Souscripteur ══ --}}
<div class="page-break"></div>

<div class="h1">L'entreprise souscriptrice</div>
<div class="spacer-lg"></div>
<table class="grid">
    <tr>
        <td class="gl">
            <div class="field-label">Raison sociale</div>
            <div class="field-value">{{ $v('company_name') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Forme juridique</div>
            <div class="field-value">{{ $v('company_legal_form') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">SIREN / SIRET</div>
            <div class="field-value">{{ $v('siret') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Date de création de l'entreprise</div>
            <div class="field-value">{{ $d('company_creation_date') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="full gl" colspan="2">
            <div class="field-label">Adresse du siège social</div>
            <div class="field-value">{{ $v('address') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Civilité du dirigeant</div>
            <div class="field-value">{{ $v('civilite') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Nom &amp; prénom du dirigeant</div>
            <div class="field-value">{{ trim($v('first_name').' '.$v('last_name')) }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Numéro de téléphone</div>
            <div class="field-value">{{ $v('phone') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Adresse e-mail</div>
            <div class="field-value">{{ $v('email') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Nombre de salariés</div>
            <div class="field-value">{{ $v('company_employee_count') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Chiffre d'affaires annuel N-1</div>
            <div class="field-value">{{ $money('company_annual_revenue') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Date de naissance du dirigeant</div>
            <div class="field-value">{{ $d('birth_date') }}&nbsp;</div>
        </td>
        <td class="gr">&nbsp;</td>
    </tr>
</table>

<div class="h2">L'activité</div>
<div class="spacer-lg"></div>
<table class="grid">
    <tr>
        <td class="gl">
            <div class="field-label">Secteur d'activité</div>
            <div class="field-value">{{ $v('company_sector') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Qualifications professionnelles</div>
            <div class="field-value">{{ $v('qualifications') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="full gl" colspan="2">
            <div class="field-label">Nature des travaux réalisés</div>
            <div class="field-value">{{ $v('activity_description') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Diplôme / formation du dirigeant</div>
            <div class="field-value">{{ $v('diploma_experience') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Années d'expérience dans l'activité</div>
            <div class="field-value">{{ $v('experience_years') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Part de sous-traitance</div>
            <div class="field-value">{{ $v('subcontracting_share') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Zone géographique d'intervention</div>
            <div class="field-value">{{ $v('intervention_area') }}&nbsp;</div>
        </td>
    </tr>
</table>

<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="h2">Les antécédents</div>
<table class="grid">
    <tr>
        <td class="gl">
            <div class="field-label">Déjà assuré pour ce risque</div>
            <div class="field-value">{{ $v('previously_insured') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Motif de résiliation antérieure</div>
            <div class="field-value">{{ $v('termination_reason') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Résilié pour non-paiement</div>
            <div class="field-value">{{ $v('terminated_non_payment') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Résilié pour fausse déclaration</div>
            <div class="field-value">{{ $v('terminated_false_declaration') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Entreprise en redressement judiciaire</div>
            <div class="field-value">{{ $v('judicial_receivership') }}&nbsp;</div>
        </td>
        <td class="gr">&nbsp;</td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Sinistres déclarés sur les 5 dernières années</div>
            <div class="field-value">{{ $v('claims_5y_count') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Montant total des sinistres</div>
            <div class="field-value">{{ $money('claims_5y_amount') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="full gl" colspan="2">
            <div class="field-label">Détail des sinistres</div>
            <div class="field-value">{{ $v('claims_5y_detail') }}&nbsp;</div>
        </td>
    </tr>
</table>

{{-- ══ Garanties ══ --}}
<div class="page-break"></div>

<div class="h1">Vos exigences et besoins</div>
<div class="spacer-lg"></div>
<table class="gtable">
    <tr>
        <th style="width: 56%;">Garantie</th>
        <th style="width: 16%;">Souscrite</th>
        <th style="width: 28%;">Franchise</th>
    </tr>
    @foreach ($garanties as $key => $label)
        <tr class="{{ $loop->even ? 'alt' : '' }}">
            <td>{{ $label }}</td>
            <td><span class="cb {{ $included($key) ? 'cb-on' : '' }}"></span></td>
            <td>{{ $v("{$key}_franchise") }}</td>
        </tr>
    @endforeach
</table>

<div class="spacer-lg"></div>
<div class="info-box">
    <p class="last">
        <strong>Droit de renonciation.</strong> Le présent contrat est souscrit par une entreprise pour les besoins de
        son activité professionnelle. La faculté de renonciation prévue à l'article L.112-2-1 du Code des assurances
        en cas de vente à distance est réservée aux personnes physiques agissant pour des besoins non professionnels ;
        elle ne s'applique donc pas au présent contrat.
    </p>
</div>

{{-- ══ Tarification ══ --}}

<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="spacer-lg"></div>
<div class="h1">Tarification</div>
<div class="spacer-lg"></div>
<table class="grid">
    <tr>
        <td class="gl">
            <div class="field-label">Formule conseillée</div>
            <div class="field-value">{{ $v('formule') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Compagnie partenaire</div>
            <div class="field-value">{{ $v('compagnie') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Date d'effet souhaitée</div>
            <div class="field-value">{{ $d('effective_date') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Échéancier</div>
            <div class="field-value">{{ $v('echeancier') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Prime annuelle (taxes incluses)</div>
            <div class="field-value">{{ $money('prime_annuelle') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Prime mensuelle (taxes incluses)</div>
            <div class="field-value">{{ $money('prime_mensuelle') }}&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td class="gl">
            <div class="field-label">Frais de courtage (gestion et service inclus)</div>
            <div class="field-value">{{ $money('frais_courtage') }}&nbsp;</div>
        </td>
        <td class="gr">&nbsp;</td>
    </tr>
</table>
<p class="body-text">
    Le client reconnaît avoir pris connaissance du contenu du présent document préalablement à la signature du
    contrat d'assurance proposé ci-dessus.
</p>

{{-- ══ Rémunération + documents ══ --}}
<div class="page-break"></div>

<div class="h1">Rémunération du courtier</div>
<p class="body-text">
    Nous sommes rémunérés sur la base d'une commission incluse dans la prime d'assurance, et de frais de courtage
    ainsi que d'honoraires dont le montant vous est communiqué sur la facture qui vous est remise par ailleurs
    (article L.521-2-II-2°d du Code des assurances). Les honoraires de courtage sont acquis au cabinet dès la
    proposition d'une solution d'assurance. En cas de rétractation dérogatoire sur la proposition d'assurance,
    le remboursement se fera uniquement au prorata des acomptes versés aux compagnies.
</p>

<div class="h1">Documents à fournir</div>
<p class="body-text">
    Nous vous livrerons votre attestation d'assurance décennale définitive avant l'expiration du certificat
    provisoire, à condition que vous nous envoyiez rapidement l'ensemble des documents suivants :
</p>
<table class="doc-list">
    @foreach ([
        'Les conditions particulières signées et paraphées',
        'Un extrait Kbis de moins de 3 mois',
        "Le relevé d'informations / historique de sinistralité décennale",
        'Les justificatifs de qualification professionnelle (le cas échéant)',
        'Le mandat SEPA signé',
        'Le RIB',
        "La copie recto-verso de la pièce d'identité du dirigeant",
        "L'acompte selon les modalités convenues avec la compagnie",
    ] as $docItem)
        <tr>
            <td class="dot"><span></span></td>
            <td>{{ $docItem }}</td>
        </tr>
    @endforeach
</table>

<div class="page-break"></div>
<div class="h1">Avertissements importants</div>
<div class="info-box">
    <p>
        Les honoraires de courtage et frais de dossier sont prélevés automatiquement en paiement différé par carte
        bancaire ; en cas de rejet de paiement, le montant réclamé sera prélevé automatiquement dès
        approvisionnement de votre compte.
    </p>
    <p>
        Après étude de vos documents par la compagnie d'assurance, il peut vous être demandé de fournir des
        documents supplémentaires avant la validation de votre dossier et l'envoi de l'attestation définitive.
    </p>
    <p>
        En cas de non-réception des documents nécessaires à la validation de votre dossier avant l'expiration du
        certificat provisoire, votre contrat d'assurance décennale sera résilié.
    </p>
    <p>
        En cas de réception de documents dont les informations ne sont pas conformes à ce que vous avez déclaré
        avant la prise de garantie provisoire, votre contrat sera résilié par la compagnie, ou un avenant vous sera
        proposé pour régulariser votre dossier.
    </p>
    <p>
        En signant ce document, vous confirmez votre accord et attestez l'exactitude de l'ensemble de vos
        déclarations relatives à l'activité de votre entreprise, à sa sinistralité et à ses antécédents d'assurance.
    </p>
    <p>
        Ce document n'est qu'un devis, accompagné d'autres documents précontractuels, et ne peut en aucun cas
        garantir que votre entreprise est assurée, même signé. Votre couverture ne devient effective qu'après
        acceptation de votre dossier par la compagnie et réception de votre attestation d'assurance.
    </p>
    <p class="last">
        En cas de fausse déclaration, le contrat sera résilié pour fausse déclaration ou nullité de contrat.
        Les modalités d'application du contrat et des garanties sont détaillées dans les dispositions particulières
        et générales.
    </p>
</div>

{{-- ══ Prélèvement + signature ══ --}}
<div class="page-break"></div>

<div class="h1">Autorisation de prélèvement</div>
<p class="body-text">
    Je soussigné(e), M./Mme <span class="fill-inline">{{ trim($v('first_name').' '.$v('last_name')) ?: '&nbsp;' }}</span>,
    agissant en qualité de représentant de <span class="fill-inline">{{ $v('company_name') ?: '&nbsp;' }}</span>,
    confirme accepter d'être prélevé(e), à réception de la facture, du règlement du compte de l'entreprise au titre
    des frais de dossier par carte bancaire. J'autorise le cabinet {{ $broker['name'] }} à débiter la somme de
    <span class="fill-inline">{{ $money('debit_amount', '&nbsp;') }}</span> à régler le
    <span class="fill-inline">{{ $d('installment_1_date', '&nbsp;') }}</span>.
</p>
@if (($data['installment_2_amount'] ?? '') !== '' || ($data['installment_2_date'] ?? '') !== '')
<p class="body-text">
    Une seconde échéance de <span class="fill-inline">{{ $money('installment_2_amount', '&nbsp;') }}</span>
    sera réglée le <span class="fill-inline">{{ $d('installment_2_date', '&nbsp;') }}</span>.
</p>
@endif
<div class="spacer-lg"></div>
<table class="grid">
    <tr>
        <td class="gl">
            <div class="field-label">Mode de paiement</div>
            <div class="field-value">{{ $v('payment_mode') }}&nbsp;</div>
        </td>
        <td class="gr">
            <div class="field-label">Plateforme de paiement</div>
            <div class="field-value">{{ $v('payment_platform') }}&nbsp;</div>
        </td>
    </tr>
</table>

@include('contracts.partials.signature-block')

</body>
</html>
