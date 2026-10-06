<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Credenciales — {{ $participation->team->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; background: #fff; }
        .sheet { width: 194mm; min-height: 281mm; margin: 0 auto; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        .grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .grid .credential-cell { width: 50%; padding: 2mm 1.5mm; vertical-align: top; }
        .credential { position: relative; width: 90mm; height: 60mm; overflow: hidden; border: .45mm solid {{ $primary }}; border-radius: 3mm; background: #f5f3ee; box-shadow: 0 1mm 2.5mm #727880; }
        .accent-circle { position: absolute; right: -14mm; bottom: -25mm; width: 64mm; height: 64mm; border: 5mm solid #71869b; border-radius: 50%; }
        .logos { position: absolute; z-index: 3; top: 0; left: 0; width: 100%; height: 9mm; border-collapse: collapse; background: #fff; }
        .logos td { height: 9mm; padding: .8mm 2mm; text-align: center; vertical-align: middle; border-right: 0; }
        .logos img { max-width: 16mm; max-height: 6.8mm; }
        .logo-empty { color: #94a3b8; font-size: 5pt; }
        .league-name { position: absolute; z-index: 3; top: 9mm; left: 0; width: 100%; height: 8.5mm; padding: 1.3mm 3mm 0; color: #fff; font-family: "DejaVu Sans Condensed", "Arial Narrow", Arial, Helvetica, sans-serif; font-size: 10.5pt; font-weight: bold; line-height: 5mm; text-align: center; text-transform: uppercase; background: {{ $primary }}; white-space: nowrap; overflow: hidden; }
        .body { position: absolute; z-index: 2; top: 17.5mm; left: 0; width: 100%; height: 41.7mm; border-collapse: collapse; }
        .photo-cell { width: 29mm; height: 41.7mm; padding: 2.5mm 1.5mm 2mm 3mm; vertical-align: top; }
        .photo { width: 24mm; height: 30.5mm; overflow: hidden; border-radius: 2mm; background: #dce6ef; text-align: center; box-shadow: 0 .7mm 1.5mm #b8c4d1; }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        .photo-empty { padding-top: 13mm; color: #64748b; font-size: 7pt; }
        .details { height: 41.7mm; padding: 2.4mm 3mm 1.7mm 0; vertical-align: top; }
        .identity { height: 8.3mm; margin-bottom: 1.2mm; padding: .9mm 2mm; overflow: hidden; border: .2mm solid #d4dde8; border-left: 1.2mm solid {{ $secondary }}; border-radius: 1.5mm; background: #fff; }
        .label { display: block; color: #3a434e; font-size: 4.5pt; font-weight: bold; line-height: 2.2mm; text-transform: uppercase; }
        .value { display: block; color: {{ $primary }}; font-size: 7.4pt; font-weight: bold; line-height: 3.6mm; white-space: nowrap; overflow: hidden; }
        .facts { width: 100%; height: 11.5mm; margin-top: .2mm; border-spacing: 1mm 0; table-layout: fixed; }
        .facts td { padding: 1mm 1.2mm; overflow: hidden; text-align: center; vertical-align: middle; border: .2mm solid #d4dde8; border-radius: 1.3mm; background: #fff; }
        .facts td:first-child { width: 40%; }
        .facts td:nth-child(2) { width: 40%; }
        .facts .jersey { width: 20%; color: #fff; border-color: {{ $primary }}; background: {{ $primary }}; }
        .fact-label { display: block; color: #1f2329; font-size: 4.1pt; font-weight: bold; line-height: 2mm; text-align: center; text-transform: uppercase; }
        .fact-value { display: block; width: 100%; color: {{ $primary }}; font-size: 5.6pt; font-weight: bold; line-height: 2.6mm; text-align: center; white-space: nowrap; overflow: hidden; }
        .jersey .fact-label, .jersey .fact-value { color: #fff; }
        .jersey .fact-value { font-size: 12pt; line-height: 5mm; }
        .footer { padding-top: 1.1mm; color: #64748b; font-size: 4.2pt; text-align: right; text-transform: uppercase; white-space: nowrap; }
        .actions { margin: 0 auto 6mm; width: 194mm; }
        .actions a, .actions button { display: inline-block; margin-right: 3mm; padding: 2.5mm 4mm; color: white; text-decoration: none; border: 0; border-radius: 2mm; background: {{ $primary }}; cursor: pointer; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    @unless ($pdfMode)
        <div class="actions">
            <button onclick="window.print()">Imprimir vista previa</button>
            <a href="/liga/plantillas/{{ $participation->id }}/credenciales.pdf">Descargar PDF</a>
        </div>
    @endunless
    @foreach ($pages as $page)
        <div class="sheet">
            <table class="grid">
                @foreach ($page->chunk(2) as $row)
                    <tr>
                        @foreach ($row as $registration)
                            <td class="credential-cell">
                                @php
                                    $visibleLogos = collect($logos)->filter()->values();
                                    $logoCount = $visibleLogos->count();
                                    $logoWidth = $logoCount > 0 ? 100 / $logoCount : 100;
                                @endphp
                                <div class="credential">
                                    <div class="accent-circle"></div>
                                    @if ($logoCount > 0)
                                        <table class="logos"><tr>
                                            @foreach ($visibleLogos as $logo)
                                                <td style="width: {{ $logoWidth }}%;"><img src="{{ $logo }}" alt="Logo"></td>
                                            @endforeach
                                        </tr></table>
                                    @endif
                                    <div class="league-name">{{ $league->name }}</div>
                                    <table class="body"><tr>
                                        <td class="photo-cell"><div class="photo">@if ($registration->photo_data)<img src="{{ $registration->photo_data }}" alt="Fotografía">@else<div class="photo-empty">SIN FOTO</div>@endif</div></td>
                                        <td class="details">
                                            <div class="identity"><span class="label">Nombre del jugador</span><span class="value">{{ $registration->player->full_name }}</span></div>
                                            <div class="identity"><span class="label">Equipo</span><span class="value">{{ $participation->team->name }}</span></div>
                                            <table class="facts"><tr>
                                                <td><span class="fact-label">División</span><span class="fact-value">{{ $participation->competition->division->name }}</span></td>
                                                <td><span class="fact-label">Posición</span><span class="fact-value">{{ match($registration->player->position) { 'goalkeeper' => 'Portero', 'defender' => 'Defensa', 'midfielder' => 'Mediocampista', 'forward' => 'Delantero', default => $registration->player->position } }}</span></td>
                                                <td class="jersey"><span class="fact-label">Dorsal</span><span class="fact-value">{{ $registration->jersey_number }}</span></td>
                                            </tr></table>
                                            <div class="footer">{{ $registration->activeCredential?->folio ?? 'FOLIO PENDIENTE' }} · {{ $participation->season->name }} · {{ $participation->competition->category->name }}</div>
                                        </td>
                                    </tr></table>
                                </div>
                            </td>
                        @endforeach
                        @if ($row->count() === 1)<td class="credential-cell"></td>@endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
