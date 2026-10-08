<?php

namespace App\Http\Controllers\League;

use App\Domain\League\Models\League;
use App\Domain\Referee\Models\Referee;
use App\Domain\Scheduling\Enums\LineupStatus;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchLineup;
use App\Domain\Scheduling\Services\MatchLineupService;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMatchLineupRequest;
use App\Http\Requests\SubmitMatchLineupRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LineupController extends Controller
{
    public function __construct(
        private readonly LeagueContext $context,
        private readonly AuditLogger $audit,
        private readonly MatchLineupService $lineups,
    ) {}

    public function index(Request $request): Response
    {
        $league = $this->league($request);
        $canManageAll = $this->hasPermission($request, $league, 'lineups.manage');
        $canManageOwn = $this->hasPermission($request, $league, 'lineups.manage-own');
        $ownedTeamIds = $canManageOwn ? $this->ownedTeamIds($request, $league) : collect();
        $refereeIds = Referee::where('league_id', $league->id)->where('user_id', $request->user()->id)->pluck('id');

        $matches = GameMatch::query()
            ->whereHas('competition.tournament.season', fn ($query) => $query->where('league_id', $league->id))
            ->whereHas('matchday', fn ($query) => $query->where('status', 'published'))
            ->whereIn('status', ['scheduled', 'in_progress', 'finished', 'suspended', 'postponed'])
            ->when(! $canManageAll && $ownedTeamIds->isNotEmpty(), fn ($query) => $query->where(function ($teams) use ($ownedTeamIds): void {
                $teams->whereHas('homeParticipation', fn ($participation) => $participation->whereIn('team_id', $ownedTeamIds))
                    ->orWhereHas('awayParticipation', fn ($participation) => $participation->whereIn('team_id', $ownedTeamIds));
            }))
            ->when(! $canManageAll && $ownedTeamIds->isEmpty() && $refereeIds->isNotEmpty(), fn ($query) => $query
                ->whereHas('refereeAssignments', fn ($assignments) => $assignments->whereIn('referee_id', $refereeIds)))
            ->when(! $canManageAll && $ownedTeamIds->isEmpty() && $refereeIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->with([
                'competition:id,name', 'matchday:id,name,number', 'field.venue:id,name',
                'refereeAssignments:id,match_id,referee_id',
                'homeParticipation.team:id,name,short_name', 'awayParticipation.team:id,name,short_name',
                'homeParticipation.playerRegistrations' => fn ($query) => $query->where('status', 'active')->whereHas('player', fn ($player) => $player->where('status', 'active'))->with('player:id,full_name,position,status')->orderBy('jersey_number'),
                'awayParticipation.playerRegistrations' => fn ($query) => $query->where('status', 'active')->whereHas('player', fn ($player) => $player->where('status', 'active'))->with('player:id,full_name,position,status')->orderBy('jersey_number'),
                'lineups.players.registration.player:id,full_name,position', 'lineups.submittedBy:id,name',
            ])
            ->orderByDesc('scheduled_at')->paginate(20)->withQueryString()
            ->through(fn (GameMatch $match) => $this->presentMatch($match, $canManageAll, $canManageOwn, $ownedTeamIds, $refereeIds));

        return Inertia::render('League/Lineups/Index', ['matches' => $matches]);
    }

    public function save(SaveMatchLineupRequest $request, GameMatch $match, TeamParticipation $participation): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertMatch($match, $league);
        $this->authorizeManagement($request, $league, $participation);
        $old = MatchLineup::where('match_id', $match->id)->where('team_participation_id', $participation->id)
            ->with('players')->first()?->toArray() ?? [];
        $lineup = $this->lineups->save($match, $participation, $request->validated('players'), $request->user());
        $this->audit->log($request, 'lineup.saved', $lineup, $league, $old, $lineup->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Alineación guardada como borrador. Todavía puedes modificarla antes de enviarla.');
    }

    public function submit(SubmitMatchLineupRequest $request, GameMatch $match, TeamParticipation $participation): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertMatch($match, $league);
        $this->authorizeManagement($request, $league, $participation);
        $old = MatchLineup::where('match_id', $match->id)->where('team_participation_id', $participation->id)
            ->with('players')->first()?->toArray() ?? [];
        $lineup = $this->lineups->submit($match, $participation, $request->user());
        $this->audit->log($request, 'lineup.submitted', $lineup, $league, $old, $lineup->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Alineación enviada y cerrada definitivamente.');
    }

    private function presentMatch(GameMatch $match, bool $canManageAll, bool $canManageOwn, $ownedTeamIds, $refereeIds): array
    {
        $isAssignedReferee = $match->refereeAssignments->pluck('referee_id')->intersect($refereeIds)->isNotEmpty();
        return [
            'id' => $match->id,
            'status' => $match->status->value,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'competition' => $match->competition,
            'matchday' => $match->matchday,
            'field' => $match->field,
            'teams' => collect([$match->homeParticipation, $match->awayParticipation])->map(function (TeamParticipation $participation) use ($match, $canManageAll, $canManageOwn, $ownedTeamIds, $isAssignedReferee): array {
                $owned = $ownedTeamIds->contains($participation->team_id);
                $lineup = $match->lineups->firstWhere('team_participation_id', $participation->id);
                $visible = $canManageAll || $owned || ($isAssignedReferee && $lineup?->status === LineupStatus::Submitted);
                $editable = ($canManageAll || ($canManageOwn && $owned))
                    && in_array($match->status->value, ['scheduled', 'postponed'], true)
                    && $lineup?->status !== LineupStatus::Submitted;
                return [
                    'participation_id' => $participation->id,
                    'name' => $participation->registered_name,
                    'team' => $participation->team,
                    'editable' => $editable,
                    'lineup' => $visible && $lineup ? [
                        'id' => $lineup->id,
                        'status' => $lineup->status->value,
                        'submitted_at' => $lineup->submitted_at?->toIso8601String(),
                        'submitted_by' => $lineup->submittedBy,
                        'players' => $lineup->players->sortBy(fn ($selection) => ($selection->role->value === 'starter' ? '0-' : '1-').str_pad((string) $selection->display_order, 5, '0', STR_PAD_LEFT))->map(fn ($selection) => [
                            'player_registration_id' => $selection->player_registration_id,
                            'name' => $selection->registration->player->full_name,
                            'jersey_number' => $selection->jersey_number,
                            'position' => $selection->position,
                            'role' => $selection->role->value,
                            'is_captain' => $selection->is_captain,
                        ])->values(),
                    ] : null,
                    'available_players' => $editable ? $participation->playerRegistrations->map(fn ($registration) => [
                        'id' => $registration->id,
                        'name' => $registration->player->full_name,
                        'jersey_number' => $registration->jersey_number,
                        'position' => $registration->player->position,
                    ])->values() : [],
                ];
            })->values(),
        ];
    }

    private function authorizeManagement(Request $request, League $league, TeamParticipation $participation): void
    {
        if ($this->hasPermission($request, $league, 'lineups.manage')) return;
        abort_unless($this->hasPermission($request, $league, 'lineups.manage-own')
            && $this->ownedTeamIds($request, $league)->contains($participation->team_id), 403);
    }

    private function hasPermission(Request $request, League $league, string $permission): bool
    {
        $roleId = (int) $request->session()->get(LeagueContext::ROLE_KEY);
        return $request->user()->hasPermission($permission) || $request->user()->hasLeaguePermission($permission, $league->id, $roleId);
    }

    private function ownedTeamIds(Request $request, League $league)
    {
        return TeamRepresentative::where('user_id', $request->user()->id)->where('status', 'active')
            ->whereHas('team', fn ($query) => $query->where('league_id', $league->id))->pluck('team_id');
    }

    private function assertMatch(GameMatch $match, League $league): void
    {
        $match->loadMissing('competition.tournament.season', 'matchday');
        abort_unless($match->competition->tournament->season->league_id === $league->id, 404);
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
}
