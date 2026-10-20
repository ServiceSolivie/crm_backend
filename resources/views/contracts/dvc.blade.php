<?php
/**
 * The one PDF view of every DVC type: the common frame (header, footer,
 * signature) around the sections of the type's JSON schema.
 *
 * @var \App\Support\Dvc\DvcDocument $dvc
 */
$fontDir = str_replace('\\', '/', storage_path('fonts'));
$heroImg = $dvc->schema->heroImage() ? str_replace('\\', '/', public_path('images/contracts/'.$dvc->schema->heroImage())) : null;
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

    /* NB : ne jamais remettre `html` (ni `*`) dans le reset ci-dessous —
       dompdf mappe la marge de l'élément html sur la boîte de page,
       ce qui annulerait les marges @page. */
    @page {
        margin: 2.8cm;
        margin-top: 1.8cm;
        margin-bottom: 1.8cm;
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
    .doc-title { text-align: center; font-size: 22pt; font-weight: 700; color: #16202c; margin-bottom: 12px; }
    .doc-lede { text-align: center; font-size: 9pt; color: #5b6b7d; width: 82%; margin: 0 auto 24px auto; line-height: 1.7; }

    .hero-wrap { text-align: center; margin: 4px 0 26px 0; }
    .hero { width: 100%; }

    /* ── Titres de section ────────────────────────── */
    .h1 { font-size: 13.5pt; font-weight: 700; color: #16202c; margin: 26px 0 12px 0; text-decoration: underline; }
    .h1 .num { font-size: 8pt; font-weight: 700; color: #2b6cb5; vertical-align: 3px; margin-right: 7px; letter-spacing: 1px; }
    .h2 { font-size: 10.5pt; font-weight: 700; color: #16202c; margin: 20px 0 8px 0; }

    p.body-text { font-size: 9pt; color: #3d4a5c; text-align: justify; margin-bottom: 9px; line-height: 1.65; }
    p.body-text strong { color: #16202c; }

    /* ── Grille label / valeur ────────────────────── */
    .grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .grid td { vertical-align: bottom; padding: 0 7px 16px 7px; }
    .grid td.first { padding-left: 0; }
    .grid td.last { padding-right: 0; }

    .field-label { font-size: 6.8pt; font-weight: 700; color: #2b6cb5; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
    .field-value { font-size: 9.5pt; color: #16202c; border-bottom: 0.75px solid #b9c6d4; padding: 1px 1px 4px 1px; min-height: 13pt; }

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

    .cb { display: inline-block; width: 10px; height: 10px; border: 1.1px solid #6aa4d8; border-radius: 2px; background: #ffffff; }
    .cb-on { background: #2b6cb5; border-color: #2b6cb5; }

    /* ── Liste ─────────────────────────────────────── */
    .doc-list { width: 100%; border-collapse: collapse; }
    .doc-list td { padding: 4.5px 0; font-size: 9pt; color: #2c3a4c; vertical-align: middle; }
    .doc-list td.dot { width: 16px; }
    .doc-list td.dot span { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: #2b6cb5; }

    /* ── Signatures ───────────────────────────────── */
    .sig-section { margin-top: 8px; }
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 10px; page-break-inside: avoid; }
    .sig-col { width: 46%; vertical-align: top; }
    .sig-gap { width: 8%; }
    .sig-box { border: 0.9px solid #b9c6d4; border-radius: 8px; height: 78px; margin-top: 4px; padding: 6px 8px; vertical-align: bottom; text-align: center; }
    .sig-img { height: 56px; margin-top: 8px; }
    .sig-hint { font-size: 7pt; color: #9aa8b8; }
    .sig-date { font-size: 8.5pt; color: #3d4a5c; margin-top: 8px; }

    .legal-footer { border-top: 0.75px solid #d3dce6; margin-top: 26px; padding-top: 10px; font-size: 6.9pt; color: #7a8797; line-height: 1.7; }

    .page-break { page-break-before: always; }
</style>
</head>
<body>

@include('contracts.partials.footer')

@include('contracts.partials.header', [
    'title' => $dvc->schema->title(),
    'subtitle' => $dvc->schema->subtitle() ?? '',
])

@if ($heroImg)
    <div class="hero-wrap">
        <img class="hero" src="{{ $heroImg }}" alt="">
    </div>
@endif

@foreach ($dvc->schema->sections() as $section)
    @continue(! $dvc->visible($section))

    @if ($section['page_break_before'] ?? false)
        <div class="page-break"></div>
    @endif

    @if (! empty($section['title']))
        @if (($section['level'] ?? 1) === 2)
            <div class="h2">{{ $section['title'] }}</div>
        @else
            <div class="h1">@if (! empty($section['number']))<span class="num">{{ $section['number'] }}</span>@endif{{ $section['title'] }}</div>
        @endif
    @endif

    @switch($section['type'])
        @case('fields')
            @php($columns = $section['columns'] ?? 2)
            <table class="grid">
                @foreach ($dvc->rows($section) as $row)
                    <tr>
                        @foreach ($row as $field)
                            @php($full = ($field['width'] ?? null) === 'full')
                            <td @class(['first' => $loop->first, 'last' => $full || $loop->iteration === $columns])
                                @if ($full) colspan="{{ $columns }}" @else style="width: {{ round(100 / $columns, 2) }}%;" @endif>
                                <div class="field-label">{{ $field['label'] }}</div>
                                <div class="field-value">{{ $dvc->value($field) }}&nbsp;</div>
                            </td>
                        @endforeach
                        @if (! (($row[0]['width'] ?? null) === 'full'))
                            @for ($i = count($row); $i < $columns; $i++)
                                <td @class(['last' => $i === $columns - 1]) style="width: {{ round(100 / $columns, 2) }}%;">&nbsp;</td>
                            @endfor
                        @endif
                    </tr>
                @endforeach
            </table>
            @break

        @case('garanties')
            <table class="gtable">
                <tr>
                    <th style="width: 56%;">Garantie</th>
                    <th style="width: 16%;">Souscrite</th>
                    <th style="width: 28%;">Franchise</th>
                </tr>
                @foreach ($section['items'] as $item)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td>{{ $item['label'] }}</td>
                        <td><span class="cb {{ $dvc->included($item['key']) ? 'cb-on' : '' }}"></span></td>
                        <td>{{ $dvc->franchise($item['key']) }}</td>
                    </tr>
                @endforeach
            </table>
            @break

        @case('text')
            @if (($section['style'] ?? 'plain') === 'info')
                <div class="info-box">
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p @class(['last' => $loop->last])>{{ $dvc->html($paragraph) }}</p>
                    @endforeach
                </div>
            @else
                @foreach ($section['paragraphs'] as $paragraph)
                    <p class="body-text">{{ $dvc->html($paragraph) }}</p>
                @endforeach
            @endif
            @break

        @case('list')
            @if (! empty($section['intro']))
                <p class="body-text">{{ $dvc->html($section['intro']) }}</p>
            @endif
            <table class="doc-list">
                @foreach ($section['items'] as $item)
                    <tr>
                        <td class="dot"><span></span></td>
                        <td>{{ $dvc->html($item) }}</td>
                    </tr>
                @endforeach
            </table>
            @break

        @case('broker')
            @include('contracts.partials.broker-legal')
            @break
    @endswitch
@endforeach

@include('contracts.partials.signature-block')

</body>
</html>
