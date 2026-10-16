<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 16mm 12mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; }
        .header { width: 100%; border-bottom: 4px solid {{ $league->secondary_color }}; padding-bottom: 9px; margin-bottom: 14px; }
        .header td { vertical-align: middle; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .league { color: {{ $league->primary_color }}; font-size: 18px; font-weight: bold; }
        h1 { margin: 3px 0 0; font-size: 15px; }
        .meta { text-align: right; line-height: 1.6; color: #526079; }
        .filters { margin-bottom: 10px; padding: 7px 9px; background: #f1f5f9; border-radius: 5px; color: #475569; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th { padding: 6px 5px; color: white; background: {{ $league->primary_color }}; border: 1px solid {{ $league->primary_color }}; text-align: left; font-size: 8px; }
        .data td { padding: 5px; border: 1px solid #d9e0e8; vertical-align: top; }
        .data tr:nth-child(even) td { background: #f8fafc; }
        .empty { padding: 28px; text-align: center; border: 1px solid #d9e0e8; color: #64748b; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; color: #64748b; font-size: 8px; }
        .footer .right { float: right; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 70px">@if($logoPath)<img src="{{ $logoPath }}" class="logo" alt="">@endif</td>
            <td><div class="league">{{ $league->name }}</div><h1>{{ $report['title'] }}</h1></td>
            <td class="meta"><strong>Folio:</strong> {{ $folio }}<br><strong>Generado:</strong> {{ $report['generated_at']->format('d/m/Y H:i') }}<br><strong>Registros:</strong> {{ $report['total'] }}</td>
        </tr>
    </table>
    @if(count($filters))
        <div class="filters"><strong>Filtros aplicados:</strong> {{ collect($filters)->map(fn($value, $key) => str($key)->replace('_id', '')->replace('_', ' ')->title().' = '.$value)->join(' · ') }}</div>
    @endif
    @if($report['total'])
        <table class="data">
            <thead><tr>@foreach($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
            <tbody>@foreach($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody>
        </table>
    @else
        <div class="empty">No existen registros para los filtros seleccionados.</div>
    @endif
    <div class="footer">Documento generado por el Sistema de la Liga Taladzi <span class="right">{{ $folio }}</span></div>
</body>
</html>
