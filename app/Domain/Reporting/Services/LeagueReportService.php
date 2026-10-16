<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Season;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\League\Models\League;
use App\Domain\Player\Models\PlayerMovement;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Referee\Models\Referee;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Statistics\Services\CompetitionStatisticsService;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeagueReportService
{
    public const TYPES = [
        'teams', 'rosters', 'schedule', 'results', 'standings', 'scorers',
        'cards', 'sanctions', 'referee_matches', 'match_reports', 'player_movements', 'season_statistics',
    ];

    public function __construct(private readonly CompetitionStatisticsService $statistics) {}

    public function definitions(): array
    {
        return [
            ['value' => 'teams', 'label' => 'Equipos por división', 'description' => 'Equipos participantes, competencia, división y estado.'],
            ['value' => 'rosters', 'label' => 'Plantillas de jugadores', 'description' => 'Jugadores registrados, dorsal, equipo y estado.'],
            ['value' => 'schedule', 'label' => 'Calendario de partidos', 'description' => 'Jornadas, horarios, campos, equipos y árbitros.'],
            ['value' => 'results', 'label' => 'Resultados oficiales', 'description' => 'Marcadores provenientes de cédulas aprobadas.'],
            ['value' => 'standings', 'label' => 'Tabla de posiciones', 'description' => 'Posiciones oficiales de una competencia.', 'requires_competition' => true],
            ['value' => 'scorers', 'label' => 'Tabla de goleadores', 'description' => 'Goles registrados en cédulas aprobadas.', 'requires_competition' => true],
            ['value' => 'cards', 'label' => 'Tarjetas y expulsiones', 'description' => 'Disciplina individual oficial.', 'requires_competition' => true],
            ['value' => 'sanctions', 'label' => 'Sanciones', 'description' => 'Sanciones, cumplimiento y fundamento administrativo.'],
            ['value' => 'referee_matches', 'label' => 'Partidos por árbitro', 'description' => 'Designaciones arbitrales por partido.'],
            ['value' => 'match_reports', 'label' => 'Cédulas oficiales', 'description' => 'Relación de cédulas aprobadas y sus folios.'],
            ['value' => 'player_movements', 'label' => 'Altas, bajas y transferencias', 'description' => 'Historial administrativo de movimientos de jugadores.'],
            ['value' => 'season_statistics', 'label' => 'Estadísticas de temporada', 'description' => 'Resumen por competencia de partidos, goles y equipos.'],
        ];
    }

    public function options(League $league): array
    {
        $seasons = Season::query()->where('league_id', $league->id)->with([
            'tournaments:id,season_id,name',
            'tournaments.competitions:id,tournament_id,division_id,category_id,name',
            'tournaments.competitions.division:id,name',
            'tournaments.competitions.category:id,name',
        ])->latest('starts_on')->get();

        $competitionIds = $seasons->flatMap(fn (Season $season) => $season->tournaments->flatMap->competitions)->pluck('id');

        return [
            'seasons' => $seasons->map(fn (Season $season) => ['id' => $season->id, 'name' => $season->name]),
            'tournaments' => $seasons->flatMap(fn (Season $season) => $season->tournaments->map(fn ($item) => ['id' => $item->id, 'season_id' => $season->id, 'name' => $item->name]))->values(),
            'competitions' => $seasons->flatMap(fn (Season $season) => $season->tournaments->flatMap(fn ($tournament) => $tournament->competitions->map(fn (Competition $competition) => ['id' => $competition->id, 'season_id' => $season->id, 'tournament_id' => $tournament->id, 'division_id' => $competition->division_id, 'name' => $competition->name, 'division' => $competition->division->name, 'category' => $competition->category->name])))->values(),
            'divisions' => $seasons->flatMap(fn (Season $season) => $season->tournaments->flatMap->competitions)->map(fn (Competition $competition) => ['id' => $competition->division->id, 'name' => $competition->division->name])->unique('id')->values(),
            'matchdays' => Matchday::query()->whereIn('competition_id', $competitionIds)->orderBy('number')->get(['id', 'competition_id', 'name', 'number']),
            'teams' => Team::query()->where('league_id', $league->id)->orderBy('name')->get(['id', 'name']),
            'players' => $league->players()->orderBy('full_name')->get(['id', 'full_name']),
            'referees' => Referee::query()->where('league_id', $league->id)->with('user:id,name')->get()->map(fn (Referee $referee) => ['id' => $referee->id, 'name' => $referee->user?->name ?? 'Árbitro '.$referee->id]),
        ];
    }

    public function generate(League $league, string $type, array $filters): array
    {
        $report = match ($type) {
            'teams' => $this->teams($league, $filters),
            'rosters' => $this->rosters($league, $filters),
            'schedule' => $this->schedule($league, $filters),
            'results' => $this->results($league, $filters),
            'standings' => $this->statisticsReport($league, $filters, 'standings'),
            'scorers' => $this->statisticsReport($league, $filters, 'scorers'),
            'cards' => $this->statisticsReport($league, $filters, 'discipline'),
            'sanctions' => $this->sanctions($league, $filters),
            'referee_matches' => $this->refereeMatches($league, $filters),
            'match_reports' => $this->matchReports($league, $filters),
            'player_movements' => $this->playerMovements($league, $filters),
            'season_statistics' => $this->seasonStatistics($league, $filters),
            default => throw ValidationException::withMessages(['type' => 'Selecciona un reporte válido.']),
        };

        return [...$report, 'generated_at' => now(), 'filters' => $filters];
    }

    private function teams(League $league, array $filters): array
    {
        $items = TeamParticipation::query()->whereHas('season', fn (Builder $q) => $q->where('league_id', $league->id))
            ->with(['team:id,name', 'season:id,name', 'competition:id,tournament_id,name', 'competition.tournament:id,name', 'division:id,name'])
            ->when($filters['season_id'] ?? null, fn (Builder $q, $id) => $q->where('season_id', $id))
            ->when($filters['tournament_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('competition', fn (Builder $c) => $c->where('tournament_id', $id)))
            ->when($filters['competition_id'] ?? null, fn (Builder $q, $id) => $q->where('competition_id', $id))
            ->when($filters['division_id'] ?? null, fn (Builder $q, $id) => $q->where('division_id', $id))
            ->when($filters['team_id'] ?? null, fn (Builder $q, $id) => $q->where('team_id', $id))
            ->orderBy('registered_name')->get();
        return $this->table('Equipos por división', ['Temporada', 'Torneo', 'Competencia', 'División', 'Equipo', 'Estado'], $items->map(fn ($item) => [$item->season->name, $item->competition->tournament->name, $item->competition->name, $item->division->name, $item->registered_name, $this->status($item->status)]));
    }

    private function rosters(League $league, array $filters): array
    {
        $items = PlayerRegistration::query()->whereHas('season', fn (Builder $q) => $q->where('league_id', $league->id))
            ->with(['player:id,full_name,position', 'team:id,name', 'season:id,name', 'competition:id,tournament_id,name', 'competition.tournament:id,name', 'division:id,name'])
            ->when($filters['season_id'] ?? null, fn (Builder $q, $id) => $q->where('season_id', $id))
            ->when($filters['tournament_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('competition', fn (Builder $c) => $c->where('tournament_id', $id)))
            ->when($filters['competition_id'] ?? null, fn (Builder $q, $id) => $q->where('competition_id', $id))
            ->when($filters['division_id'] ?? null, fn (Builder $q, $id) => $q->where('division_id', $id))
            ->when($filters['team_id'] ?? null, fn (Builder $q, $id) => $q->where('team_id', $id))
            ->when($filters['player_id'] ?? null, fn (Builder $q, $id) => $q->where('player_id', $id))
            ->orderBy('team_id')->orderBy('jersey_number')->get();
        return $this->table('Plantillas de jugadores', ['Temporada', 'Competencia', 'División', 'Equipo', 'Dorsal', 'Jugador', 'Posición', 'Estado'], $items->map(fn ($item) => [$item->season->name, $item->competition->name, $item->division->name, $item->team->name, $item->jersey_number, $item->player->full_name, $this->status($item->player->position), $this->status($item->status)]));
    }

    private function schedule(League $league, array $filters): array
    {
        $items = $this->matchQuery($league, $filters)->with(['competition:id,name', 'matchday:id,name,number', 'homeParticipation:id,registered_name', 'awayParticipation:id,registered_name', 'field:id,venue_id,name', 'field.venue:id,name', 'refereeAssignments.referee.user:id,name'])->orderBy('scheduled_at')->get();
        return $this->table('Calendario de partidos', ['Competencia', 'Jornada', 'Fecha y hora', 'Local', 'Visitante', 'Campo', 'Árbitros', 'Estado'], $items->map(fn ($match) => [$match->competition->name, $match->matchday->name, $this->dateTime($match->scheduled_at), $match->homeParticipation->registered_name, $match->awayParticipation->registered_name, $match->field ? $match->field->venue->name.' · '.$match->field->name : 'Por confirmar', $match->refereeAssignments->map(fn ($assignment) => $assignment->referee->user?->name)->filter()->join(', '), $this->status($match->status)]));
    }

    private function results(League $league, array $filters): array
    {
        $items = MatchReport::query()->where('status', 'approved')->whereHas('match', fn (Builder $q) => $this->applyMatchFilters($q, $league, $filters))
            ->with(['match.matchday:id,name', 'match.homeParticipation:id,registered_name', 'match.awayParticipation:id,registered_name'])->latest('approved_at')->get();
        return $this->table('Resultados oficiales', ['Folio', 'Jornada', 'Fecha', 'Local', 'Marcador', 'Visitante', 'Aprobada'], $items->map(fn ($report) => [$report->folio, $report->match->matchday->name, $this->dateTime($report->match->scheduled_at), $report->match->homeParticipation->registered_name, $report->home_score.' - '.$report->away_score, $report->match->awayParticipation->registered_name, $this->dateTime($report->approved_at)]));
    }

    private function statisticsReport(League $league, array $filters, string $section): array
    {
        $competition = $this->competition($league, $filters);
        $stats = $this->statistics->calculate($competition);
        return match ($section) {
            'standings' => $this->table('Tabla de posiciones · '.$competition->name, ['Pos.', 'Equipo', 'JJ', 'JG', 'JE', 'JP', 'GF', 'GC', 'DG', 'Pts.'], collect($stats['standings'])->map(fn ($row) => [$row['position'], $row['team'], $row['played'], $row['won'], $row['drawn'], $row['lost'], $row['goals_for'], $row['goals_against'], $row['goal_difference'], $row['points']])),
            'scorers' => $this->table('Tabla de goleadores · '.$competition->name, ['Pos.', 'Jugador', 'Equipo', 'Goles'], collect($stats['scorers'])->values()->map(fn ($row, $index) => [$index + 1, $row['player'], $row['team'], $row['goals']])),
            default => $this->table('Tarjetas y expulsiones · '.$competition->name, ['Jugador', 'Equipo', 'Amarillas', 'Segunda amarilla', 'Rojas'], collect($stats['discipline'])->map(fn ($row) => [$row['player'], $row['team'], $row['yellow_cards'], $row['second_yellows'], $row['red_cards']])),
        };
    }

    private function sanctions(League $league, array $filters): array
    {
        $items = Sanction::query()->where('league_id', $league->id)->with(['season:id,name', 'competition:id,name', 'team:id,name'])
            ->when($filters['season_id'] ?? null, fn (Builder $q, $id) => $q->where('season_id', $id))->when($filters['competition_id'] ?? null, fn (Builder $q, $id) => $q->where('competition_id', $id))->when($filters['team_id'] ?? null, fn (Builder $q, $id) => $q->where('team_id', $id))->when($filters['player_id'] ?? null, fn (Builder $q, $id) => $q->where('player_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('issued_at', '>=', $date))->when($filters['date_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('issued_at', '<=', $date))->latest('issued_at')->get();
        return $this->table('Sanciones', ['Referencia', 'Fecha', 'Competencia', 'Sujeto', 'Equipo', 'Tipo', 'Pendientes', 'Estado', 'Motivo', 'Fundamento'], $items->map(fn ($item) => ['SAN-'.str_pad((string) $item->id, 6, '0', STR_PAD_LEFT), $item->issued_at?->format('d/m/Y'), $item->competition?->name, $item->subject_name, $item->team?->name, $this->status($item->kind), max(0, $item->matches_total - $item->matches_served), $this->status($item->status), $item->reason, $item->legal_basis]));
    }

    private function refereeMatches(League $league, array $filters): array
    {
        $items = $this->matchQuery($league, $filters)->whereHas('refereeAssignments', fn (Builder $q) => $q->when($filters['referee_id'] ?? null, fn (Builder $r, $id) => $r->where('referee_id', $id)))
            ->with(['competition:id,name', 'matchday:id,name', 'homeParticipation:id,registered_name', 'awayParticipation:id,registered_name', 'refereeAssignments.referee.user:id,name'])->orderBy('scheduled_at')->get();
        $rows = $items->flatMap(fn ($match) => $match->refereeAssignments->filter(fn ($assignment) => ! ($filters['referee_id'] ?? null) || $assignment->referee_id === (int) $filters['referee_id'])->map(fn ($assignment) => [$assignment->referee->user?->name, $this->status($assignment->role), $match->competition->name, $match->matchday->name, $this->dateTime($match->scheduled_at), $match->homeParticipation->registered_name.' vs '.$match->awayParticipation->registered_name, $this->status($match->status)]));
        return $this->table('Partidos por árbitro', ['Árbitro', 'Función', 'Competencia', 'Jornada', 'Fecha y hora', 'Partido', 'Estado'], $rows);
    }

    private function matchReports(League $league, array $filters): array
    {
        $items = MatchReport::query()->where('status', 'approved')->whereHas('match', fn (Builder $q) => $this->applyMatchFilters($q, $league, $filters))->with(['match.competition:id,name', 'match.matchday:id,name', 'match.homeParticipation:id,registered_name', 'match.awayParticipation:id,registered_name'])->latest('approved_at')->get();
        return $this->table('Cédulas oficiales', ['Folio', 'Competencia', 'Jornada', 'Partido', 'Marcador', 'Aprobada'], $items->map(fn ($report) => [$report->folio, $report->match->competition->name, $report->match->matchday->name, $report->match->homeParticipation->registered_name.' vs '.$report->match->awayParticipation->registered_name, $report->home_score.' - '.$report->away_score, $this->dateTime($report->approved_at)]));
    }

    private function playerMovements(League $league, array $filters): array
    {
        $items = PlayerMovement::query()->whereHas('player', fn (Builder $q) => $q->where('league_id', $league->id))->with(['player:id,full_name', 'fromTeam:id,name', 'toTeam:id,name', 'registration:id,season_id,competition_id,division_id'])
            ->when($filters['player_id'] ?? null, fn (Builder $q, $id) => $q->where('player_id', $id))->when($filters['team_id'] ?? null, fn (Builder $q, $id) => $q->where(fn (Builder $team) => $team->where('from_team_id', $id)->orWhere('to_team_id', $id)))
            ->when($filters['season_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('registration', fn (Builder $r) => $r->where('season_id', $id)))->when($filters['competition_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('registration', fn (Builder $r) => $r->where('competition_id', $id)))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('occurred_at', '>=', $date))->when($filters['date_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('occurred_at', '<=', $date))->latest('occurred_at')->get();
        return $this->table('Altas, bajas y transferencias', ['Referencia', 'Fecha', 'Jugador', 'Movimiento', 'Equipo anterior', 'Equipo nuevo', 'Motivo'], $items->map(fn ($item) => ['MOV-'.str_pad((string) $item->id, 6, '0', STR_PAD_LEFT), $this->dateTime($item->occurred_at), $item->player->full_name, $this->status($item->type), $item->fromTeam?->name, $item->toTeam?->name, $item->reason]));
    }

    private function seasonStatistics(League $league, array $filters): array
    {
        $competitions = Competition::query()->whereHas('tournament.season', fn (Builder $q) => $q->where('league_id', $league->id))->with(['tournament.season:id,name', 'division:id,name', 'category:id,name'])
            ->when($filters['season_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('tournament', fn (Builder $t) => $t->where('season_id', $id)))->when($filters['tournament_id'] ?? null, fn (Builder $q, $id) => $q->where('tournament_id', $id))->when($filters['competition_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))->when($filters['division_id'] ?? null, fn (Builder $q, $id) => $q->where('division_id', $id))->get();
        return $this->table('Estadísticas de temporada', ['Temporada', 'Competencia', 'División', 'Categoría', 'Equipos', 'Partidos oficiales', 'Goles', 'Promedio'], $competitions->map(function (Competition $competition): array { $summary = $this->statistics->calculate($competition)['summary']; return [$competition->tournament->season->name, $competition->name, $competition->division->name, $competition->category->name, $summary['registered_teams'], $summary['official_matches'], $summary['total_goals'], $summary['average_goals']]; }));
    }

    private function matchQuery(League $league, array $filters): Builder
    {
        $query = GameMatch::query();
        $this->applyMatchFilters($query, $league, $filters);
        return $query;
    }

    private function applyMatchFilters(Builder $query, League $league, array $filters): Builder
    {
        return $query->whereHas('competition.tournament.season', fn (Builder $q) => $q->where('league_id', $league->id))
            ->when($filters['season_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('competition.tournament', fn (Builder $t) => $t->where('season_id', $id)))
            ->when($filters['tournament_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('competition', fn (Builder $c) => $c->where('tournament_id', $id)))
            ->when($filters['competition_id'] ?? null, fn (Builder $q, $id) => $q->where('competition_id', $id))->when($filters['matchday_id'] ?? null, fn (Builder $q, $id) => $q->where('matchday_id', $id))
            ->when($filters['team_id'] ?? null, fn (Builder $q, $id) => $q->where(fn (Builder $team) => $team->whereHas('homeParticipation', fn (Builder $p) => $p->where('team_id', $id))->orWhereHas('awayParticipation', fn (Builder $p) => $p->where('team_id', $id))))
            ->when($filters['referee_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('refereeAssignments', fn (Builder $r) => $r->where('referee_id', $id)))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('scheduled_at', '>=', $date))->when($filters['date_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('scheduled_at', '<=', $date));
    }

    private function competition(League $league, array $filters): Competition
    {
        if (! ($filters['competition_id'] ?? null)) throw ValidationException::withMessages(['competition_id' => 'Este reporte requiere seleccionar una competencia.']);
        return Competition::query()->whereKey($filters['competition_id'])->whereHas('tournament.season', fn (Builder $q) => $q->where('league_id', $league->id))->firstOrFail();
    }

    private function table(string $title, array $columns, Collection $rows): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => $rows->map(fn ($row) => array_values($row))->values()->all(), 'total' => $rows->count()];
    }

    private function dateTime($value): string { return $value?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? ''; }
    private function status($value): string
    {
        $raw = $value instanceof \BackedEnum ? $value->value : (string) ($value ?? '');
        return str($raw)->replace('_', ' ')->title()->toString();
    }
}
