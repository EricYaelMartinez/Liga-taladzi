        <!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Credenciales — {{ $participation->team->name }}</title>
    <style>
        /* ProLiga — plantilla de impresión 90 x 60 mm, compatible con DomPDF.
           Mantiene las variables y relaciones de la plantilla original. */
        @page { size: A4 portrait; margin: 7mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1c2936; background: #fff; font-family: DejaVu Sans, Arial, sans-serif; }
        .sheet { width: 194mm; min-height: 281mm; margin: 0 auto; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        .grid { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .credential-cell { width: 50%; padding: 2mm 1.5mm; vertical-align: top; }

        .credential {
            position: relative; width: 90mm; height: 60mm; overflow: hidden;
            border: .35mm solid #d5dde4; border-radius: 3mm;
            background: #fff; box-shadow: 0 .6mm 1.8mm #b5c0c9;
        }
        /* Fondo sutil. Los elementos exteriores nunca interfieren con los datos. */
        .background-sweep {
            position: absolute; top: 39mm; right: -17mm; width: 44mm; height: 44mm;
            border: 2.4mm solid #e9eef2; border-radius: 50%; z-index: 0;
        }
        /* Esquina angular: solo bordes CSS (sin clip-path ni transforms).
           El filete secundario se dibuja antes que el bloque principal. */
        .corner-secondary {
            position: absolute; top: 0; right: 0; width: 29mm; height: 14mm;
            background: {{ $secondary }}; z-index: 1;
            border-bottom-left-radius: 20px;
        }
        .corner-secondary-tip {
            position: absolute; top: 0; right: 24.6mm; width: 0; height: 0;
            border-top: 14.5mm solid {{ $secondary }};
            border-left: 17.5mm solid transparent; z-index: 1;
            border-bottom-right-radius: 15px;
            border-bottom-left-radius: 15px;
        }
        .corner-primary {
            position: absolute; top: 0; right: 0; width: 29mm; height: 14.3mm;
            background: {{ $primary }}; z-index: 2;
            border-bottom-left-radius: 20px;
        }
        .corner-primary-tip {
            position: absolute; top: 0; right: 26.3mm; width: 0; height: 0;
            border-top: 14.1mm solid {{ $primary }};
            border-left: 11mm solid transparent; z-index: 2;
            border-bottom-right-radius: 25px;
        }
        /* Logos institucionales configurables: si hay 1, 2, 3 o 4,
           se reparten equitativamente sin separadores. */
        .logos {
            position: absolute; top: 1.7mm; left: 2.7mm;
            width: 42mm; height: 11.7mm; table-layout: fixed;
            border-collapse: collapse; z-index: 4;
        }
        .logos td { height: 11.7mm; padding: .5mm; text-align: center; vertical-align: middle; border: 0; }
        .logos img { max-width: 100%; max-height: 10.5mm; }
        .brand-placeholder {
            position: absolute; left: 3mm; top: 5mm; color: {{ $primary }};
            font-size: 9pt; font-weight: bold;
        }
        /* Nombre de la liga dentro de la franja angular superior derecha. */
        .header-text {
            position: absolute; top: 2.3mm; right: 2.3mm; width: 28mm;
            height: 12.5mm; color: #fff; text-align: center; z-index: 5;
        }
        .header-label { font-size: 4.5pt; font-weight: bold; line-height: 2.8mm; text-transform: uppercase; }
        .league-name {
            font-family: DejaVu Sans Condensed, DejaVu Sans, Arial, sans-serif;
            font-size: 7pt; line-height: 3.5mm; font-weight: bold;
            text-transform: uppercase; overflow: hidden;
            overflow-wrap: break-word;
        }
        /* Una línea fina remata el diseño deportivo de la esquina. */
        .top-accent { position: absolute; top: 0; left: 2.8mm; width: 48mm; height: .7mm; background: {{ $secondary }}; z-index: 3; }

        .photo {
            position: absolute; left: 3.4mm; top: 19.3mm;
            width: 23.7mm; height: 34mm; overflow: hidden;
            border-radius: 1.5mm; background: #dfe8ec;
            border: .25mm solid #d4dce4; z-index: 3; text-align: center;
        }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        .photo-empty { padding-top: 15mm; font-size: 7pt; color: #607381; }
        .photo-bottom { position: absolute; z-index: 4; left: 3.4mm; top: 52.7mm; width: 23.7mm; height: .9mm; background: {{ $secondary }}; }

        .details { position: absolute; z-index: 3; top: 19.3mm; left: 30mm; width: 56.5mm; }
        .field { margin-bottom: 1.3mm; }
        .field-label {
            display: block; font-size: 4.45pt; line-height: 2.4mm;
            color: #586774; font-weight: bold; text-transform: uppercase;
        }
        .field-value {
            display: block; width: 100%; max-height: 7.1mm;
            color: #192b3e; font-size: 8.5pt; line-height: 3.5mm;
            font-weight: bold; overflow: hidden;
        }
        .team-value { color: {{ $primary }}; font-size: 7.2pt; }
        .facts { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: .7mm 0; margin: .5mm 0 0 -.7mm; }
        .facts td { height: 10.3mm; padding: .75mm .6mm; border: .2mm solid #dce4e9; background: #f7f9fa; vertical-align: middle; text-align: center; }
        .facts .division-cell { width: 39%; }
        .facts .position-cell { width: 39%; }
        .facts .jersey { width: 22%; border-color: {{ $primary }}; background: {{ $primary }}; }
        .fact-label { display: block; color: #526270; font-size: 4pt; font-weight: bold; line-height: 2.3mm; text-transform: uppercase; }
        .fact-value { display: block; color: #1d2d3c; font-size: 5.3pt; font-weight: bold; line-height: 3mm; overflow: hidden; }
        .jersey .fact-label, .jersey .fact-value { color: #fff; }
        .jersey .fact-value { font-size: 12pt; line-height: 5.6mm; }

        /* Pie: se conserva folio + temporada + categoría (sin QR). */
        .footer {
            position: absolute; left: 29.9mm; right: 3mm; bottom: 2.1mm;
            height: 6.8mm; z-index: 3; border-top: .25mm solid #dce4e9;
            padding-top: .75mm; overflow: hidden;
        }
        .folio { color: {{ $primary }}; font-size: 5.5pt; line-height: 2.7mm; font-weight: bold; }
        .season-category { color: #63717d; font-size: 4.35pt; line-height: 2.6mm; text-transform: uppercase; }
        .actions { margin: 0 auto 6mm; width: 194mm; }
        .actions a, .actions button { display: inline-block; margin-right: 3mm; padding: 2.5mm 4mm; color: #fff; border: 0; border-radius: 2mm; background: {{ $primary }}; text-decoration: none; cursor: pointer; }
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
                                    $visibleLogos = collect($logos)->filter()->take(4)->values();
                                    $logoCount = $visibleLogos->count();
                                    $logoWidth = $logoCount > 0 ? 100 / $logoCount : 100;
                                    $positionName = match($registration->player->position) {
                                        'goalkeeper' => 'Portero',
                                        'defender' => 'Defensa',
                                        'midfielder' => 'Mediocampista',
                                        'forward' => 'Delantero',
                                        default => $registration->player->position,
                                    };
                                @endphp
                                <div class="credential">
                                    <div class="background-sweep"></div>
                                    <div class="corner-secondary"></div>
                                    <div class="corner-secondary-tip"></div>
                                    <div class="corner-primary"></div>
                                    <div class="corner-primary-tip"></div>
                                    <div class="top-accent"></div>

                                    @if ($logoCount > 0)
                                        <table class="logos"><tr>
                                            @foreach ($visibleLogos as $logo)
                                                <td style="width: {{ $logoWidth }}%;"><img src="{{ $logo }}" alt="Logo institucional"></td>
                                            @endforeach
                                        </tr></table>
                                    @else
                                        <div class="brand-placeholder">{{ $league->name }}</div>
                                    @endif

                                    <div class="header-text">
                                        <div class="league-name">{{ $league->name }}</div>
                                    </div>

                                    <div class="photo">
                                        @if ($registration->photo_data)
                                            <img src="{{ $registration->photo_data }}" alt="Fotografía del jugador">
                                        @else
                                            <div class="photo-empty">SIN FOTO</div>
                                        @endif
                                    </div>
                                    <div class="photo-bottom"></div>

                                    <div class="details">
                                        <div class="field">
                                            <span class="field-label">Nombre del jugador</span>
                                            <span class="field-value">{{ $registration->player->full_name }}</span>
                                        </div>
                                        <div class="field">
                                            <span class="field-label">Equipo</span>
                                            <span class="field-value team-value">{{ $participation->team->name }}</span>
                                        </div>
                                        <table class="facts"><tr>
                                            <td class="division-cell">
                                                <span class="fact-label">División</span>
                                                <span class="fact-value">{{ $participation->competition->division->name }}</span>
                                            </td>
                                            <td class="position-cell">
                                                <span class="fact-label">Posición</span>
                                                <span class="fact-value">{{ $positionName }}</span>
                                            </td>
                                            <td class="jersey">
                                                <span class="fact-label">Dorsal</span>
                                                <span class="fact-value">{{ $registration->jersey_number }}</span>
                                            </td>
                                        </tr></table>
                                    </div>
                                    <div class="footer">
                                        <div class="folio">ID: {{ $registration->activeCredential?->folio ?? 'FOLIO PENDIENTE' }}</div>
                                        <div class="season-category">{{ $participation->season->name }} · {{ $participation->competition->category->name }}</div>
                                    </div>
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
