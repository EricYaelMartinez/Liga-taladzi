<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cédula {{ $report->folio }}</title>
    <style>
        @page { margin: 14mm; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { padding: 18px; color: white; background: {{ $league->primary_color }}; border-bottom: 6px solid {{ $league->secondary_color }}; }
        h1 { margin: 0 0 5px; font-size: 20px; }
        h2 { margin: 18px 0 8px; color: {{ $league->primary_color }}; font-size: 13px; }
        .muted { color: #64748b; }
        .score { margin: 16px 0; padding: 14px; border: 1px solid #cbd5e1; text-align: center; font-size: 18px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px; border: 1px solid #cbd5e1; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        .box { min-height: 45px; padding: 9px; border: 1px solid #cbd5e1; white-space: pre-wrap; }
        .signatures { margin-top: 18px; }
        .status { float: right; padding: 5px 9px; color: #172033; background: {{ $league->secondary_color }}; font-weight: bold; }
        .lineups td { width: 50%; }
        .player { margin-bottom: 3px; }
        .role { margin: 6px 0 4px; color: #64748b; font-size: 8px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header"><span class="status">{{ strtoupper($report->status->value) }}</span><h1>{{ $league->name }}</h1><div>CÉDULA ARBITRAL · {{ $report->folio ?: 'BORRADOR' }}</div></div>
    <p><strong>{{ $match->competition->name }}</strong> · {{ $match->matchday->name }}<br>{{ $match->scheduled_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $match->field?->venue?->name }} {{ $match->field?->name }}</p>
    <div class="score">{{ $match->homeParticipation->registered_name }} &nbsp; {{ $report->home_score }} – {{ $report->away_score }} &nbsp; {{ $match->awayParticipation->registered_name }}</div>
    @if($report->home_no_show || $report->away_no_show)<p><strong>Incomparecencia:</strong> {{ $report->home_no_show ? $match->homeParticipation->registered_name : $match->awayParticipation->registered_name }}</p>@endif

    @php
        $homeLineup = $match->lineups->firstWhere('team_participation_id', $match->homeParticipation->id);
        $awayLineup = $match->lineups->firstWhere('team_participation_id', $match->awayParticipation->id);
    @endphp
    <h2>Alineaciones registradas</h2>
    <table class="lineups">
        <thead><tr><th>{{ $match->homeParticipation->registered_name }}</th><th>{{ $match->awayParticipation->registered_name }}</th></tr></thead>
        <tbody><tr>
            @foreach([$homeLineup, $awayLineup] as $lineup)
                <td>
                    @if($lineup && $lineup->status->value === 'submitted')
                        <div class="role">Titulares</div>
                        @foreach($lineup->players->where('role', \App\Domain\Scheduling\Enums\LineupPlayerRole::Starter)->sortBy('display_order') as $selection)
                            <div class="player"><strong>#{{ $selection->jersey_number ?? 'S/N' }}</strong> {{ $selection->registration?->player?->full_name }}{{ $selection->is_captain ? ' (C)' : '' }}</div>
                        @endforeach
                        @if($lineup->players->where('role', \App\Domain\Scheduling\Enums\LineupPlayerRole::Substitute)->isNotEmpty())
                            <div class="role">Suplentes</div>
                            @foreach($lineup->players->where('role', \App\Domain\Scheduling\Enums\LineupPlayerRole::Substitute)->sortBy('display_order') as $selection)
                                <div class="player"><strong>#{{ $selection->jersey_number ?? 'S/N' }}</strong> {{ $selection->registration?->player?->full_name }}{{ $selection->is_captain ? ' (C)' : '' }}</div>
                            @endforeach
                        @endif
                    @else
                        <span class="muted">Sin alineación digital enviada.</span>
                    @endif
                </td>
            @endforeach
        </tr></tbody>
    </table>

    <h2>Eventos del encuentro</h2>
    <table><thead><tr><th>Min.</th><th>Periodo</th><th>Tipo</th><th>Equipo</th><th>Jugador</th><th>Detalle</th></tr></thead><tbody>
    @forelse($report->events as $event)
        <tr><td>{{ $event->minute ?? '—' }}</td><td>{{ $event->period }}</td><td>{{ $event->type }}</td><td>{{ $event->teamParticipation?->registered_name ?? 'General' }}</td><td>{{ $event->playerRegistration?->player?->full_name ?? '—' }}@if($event->relatedPlayerRegistration) → {{ $event->relatedPlayerRegistration->player->full_name }}@endif</td><td>{{ $event->notes ?: '—' }}</td></tr>
    @empty<tr><td colspan="6">Sin eventos registrados.</td></tr>@endforelse
    </tbody></table>

    <h2>Resumen</h2><div class="box">{{ $report->summary ?: 'Sin resumen.' }}</div>
    <h2>Incidentes y observaciones</h2><div class="box">{{ $report->incidents ?: 'Sin incidentes.' }}</div>
    <h2>Conformidad de representantes</h2>
    <table class="signatures"><thead><tr><th>Equipo</th><th>Representante</th><th>Decisión</th><th>Fecha y hora</th><th>Observaciones</th></tr></thead><tbody>
    @forelse($report->confirmations as $confirmation)<tr><td>{{ $confirmation->teamParticipation->registered_name }}</td><td>{{ $confirmation->representative?->name ?? '—' }}</td><td>{{ $confirmation->decision }}</td><td>{{ $confirmation->decided_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Pendiente' }}</td><td>{{ $confirmation->notes ?: '—' }}</td></tr>@empty<tr><td colspan="5">Pendiente de envío.</td></tr>@endforelse
    </tbody></table>
    @if($report->review_notes)<h2>Revisión administrativa</h2><div class="box">{{ $report->review_notes }}</div>@endif
    <p class="muted">Generada por el Sistema de la Liga Taladzi. El historial de usuario, fecha y hora se conserva en la bitácora.</p>
</body>
</html>
