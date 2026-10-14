<?php

namespace App\Http\Controllers\League;

use App\Domain\League\Models\League;
use App\Domain\Discipline\Services\DisciplineService;
use App\Domain\Referee\Models\Referee;
use App\Domain\Scheduling\Enums\MatchReportStatus;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Services\MatchReportService;
use App\Domain\Scheduling\Services\MatchLifecycleService;
use App\Domain\Team\Models\TeamRepresentative;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmMatchReportRequest;
use App\Http\Requests\ReviewMatchReportRequest;
use App\Http\Requests\SaveMatchReportRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class MatchReportController extends Controller
{
    public function __construct(private readonly LeagueContext $context, private readonly MatchReportService $reports, private readonly AuditLogger $audit, private readonly MatchLifecycleService $lifecycle, private readonly DisciplineService $discipline) {}

    public function index(Request $request): InertiaResponse
    {
        $league = $this->league($request);
        $this->lifecycle->syncForLeague($league->id);
        $canReview = $this->hasPermission($request, $league, 'reports.review');
        $refereeIds = Referee::where('league_id', $league->id)->where('user_id', $request->user()->id)->pluck('id');
        $ownedTeamIds = TeamRepresentative::where('user_id', $request->user()->id)->where('status', 'active')
            ->whereHas('team', fn ($query) => $query->where('league_id', $league->id))->pluck('team_id');

        $matches = GameMatch::query()
            ->whereHas('competition.tournament.season', fn ($query) => $query->where('league_id', $league->id))
            ->whereHas('matchday', fn ($query) => $query->whereIn('status', ['published', 'completed']))
            ->whereIn('status', ['scheduled', 'in_progress', 'finished', 'suspended'])
            ->when(! $canReview, fn ($query) => $query->where(function ($scope) use ($refereeIds, $ownedTeamIds): void {
                $scope->whereHas('refereeAssignments', fn ($assignments) => $assignments->whereIn('referee_id', $refereeIds))
                    ->orWhereHas('homeParticipation', fn ($participation) => $participation->whereIn('team_id', $ownedTeamIds))
                    ->orWhereHas('awayParticipation', fn ($participation) => $participation->whereIn('team_id', $ownedTeamIds));
            }))
            ->with($this->matchRelations())
            ->orderByDesc('scheduled_at')->paginate(15)->withQueryString()
            ->through(fn (GameMatch $match) => $this->present($request, $match, $league, $canReview, $refereeIds));

        return Inertia::render('League/Reports/Index', ['matches' => $matches]);
    }

    public function save(SaveMatchReportRequest $request, GameMatch $match): RedirectResponse
    {
        $league = $this->league($request); $this->assertMatch($match, $league); $match = $this->lifecycle->syncMatch($match); $this->authorizeEdit($request, $match, $league);
        $old = $match->report?->load('events')->toArray() ?? [];
        $report = $this->reports->save($match->load('homeParticipation', 'awayParticipation'), $request->safe()->except('reason'), $request->user());
        $this->audit->log($request, 'match_report.saved', $report, $league, $old, $report->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Cédula arbitral guardada como borrador.');
    }

    public function submit(Request $request, GameMatch $match): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $league = $this->league($request); $this->assertMatch($match, $league); $match = $this->lifecycle->syncMatch($match); $this->authorizeCentral($request, $match, $league);
        $report = $this->reports->submit($match->load('homeParticipation', 'awayParticipation'), $request->user(), $league->id);
        $this->audit->log($request, 'match_report.submitted', $report, $league, [], ['status' => $report->status->value, 'folio' => $report->folio], $data['reason']);
        return back()->with('success', 'Cédula enviada a conformidad de los dos representantes.');
    }

    public function confirm(ConfirmMatchReportRequest $request, MatchReport $report): RedirectResponse
    {
        $league = $this->league($request); $this->assertReport($report, $league);
        $confirmation = $report->confirmations()->where('representative_user_id', $request->user()->id)->firstOrFail();
        $updated = $this->reports->confirm($report, $confirmation, $request->validated('decision'), $request->validated('notes'), $request->user());
        $this->audit->log($request, 'match_report.confirmed', $updated, $league, [], ['decision' => $request->validated('decision')], $request->validated('notes'));
        return back()->with('success', $request->validated('decision') === 'confirmed' ? 'Conformidad registrada con fecha, hora y usuario.' : 'Inconformidad registrada; la cédula fue devuelta para corrección.');
    }

    public function review(ReviewMatchReportRequest $request, MatchReport $report): RedirectResponse
    {
        $league = $this->league($request); $this->assertReport($report, $league);
        abort_unless($this->hasPermission($request, $league, 'reports.review'), 403);
        $old = $report->toArray();
        $updated = $this->reports->review($report, $request->validated('action'), $request->validated('notes'), $request->user());
        $this->audit->log($request, 'match_report.reviewed', $updated, $league, $old, $updated->toArray(), $request->validated('notes'));
        if ($request->validated('action') === 'approve') {
            foreach ($this->discipline->syncReport($updated) as $sanction) {
                $this->audit->log($request, 'discipline.sanction.automatic', $sanction, $league, [], $sanction->toArray(), $sanction->reason);
            }
        }
        return back()->with('success', $request->validated('action') === 'approve' ? 'Cédula aprobada y resultado oficial registrado.' : 'Cédula devuelta al árbitro para corrección.');
    }

    public function download(Request $request, MatchReport $report): Response
    {
        $league = $this->league($request); $this->assertReport($report, $league);
        $match = $report->match()->with($this->matchRelations())->firstOrFail();
        $this->authorizeView($request, $match, $league);
        abort_unless($report->status === MatchReportStatus::Approved, 403, 'El PDF oficial estará disponible cuando la cédula sea aprobada.');
        $report->load('events.playerRegistration.player', 'events.relatedPlayerRegistration.player', 'events.teamParticipation', 'confirmations.representative', 'confirmations.teamParticipation.team', 'submittedBy', 'reviewedBy');
        return Pdf::loadView('reports.match', compact('league', 'match', 'report'))->setPaper('a4', 'portrait')->download('cedula-'.($report->folio ?: $match->id).'.pdf');
    }

    private function present(Request $request, GameMatch $match, League $league, bool $canReview, $refereeIds): array
    {
        $central = $match->refereeAssignments->contains(fn ($assignment) => $assignment->role === 'central' && $refereeIds->contains($assignment->referee_id));
        $report = $match->report;
        $captureAvailable = $match->scheduled_at && ! $match->scheduled_at->isFuture()
            && in_array($match->status->value, ['in_progress', 'suspended', 'finished'], true);
        $editableStatus = ! $report || in_array($report->status, [MatchReportStatus::Draft, MatchReportStatus::CorrectionRequired], true);
        $confirmation = $report?->confirmations->firstWhere('representative_user_id', $request->user()->id);
        $assigned = $match->refereeAssignments->contains(fn ($assignment) => $refereeIds->contains($assignment->referee_id));
        $canViewReport = $report !== null && ($report->status !== MatchReportStatus::Draft || $canReview || $assigned);
        return [
            'id' => $match->id, 'status' => $match->status->value, 'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'competition' => $match->competition, 'matchday' => $match->matchday, 'field' => $match->field,
            'home' => $this->teamData($match, $match->homeParticipation), 'away' => $this->teamData($match, $match->awayParticipation),
            'officials' => $match->refereeAssignments->map(fn ($assignment): array => [
                'role' => $assignment->role,
                'name' => $assignment->referee->user?->name ?? 'Árbitro sin usuario',
            ])->values(),
            'report' => $canViewReport ? $this->reportData($report) : null,
            'permissions' => ['capture_available' => $captureAvailable, 'edit' => $captureAvailable && $editableStatus && ($canReview || ($central && $this->hasPermission($request, $league, 'reports.manage'))), 'submit' => $captureAvailable && $editableStatus && $central, 'view' => (bool) $canViewReport, 'confirm' => $report?->status === MatchReportStatus::AwaitingConfirmations && $confirmation?->decision === 'pending', 'review' => $canReview && $report?->status === MatchReportStatus::UnderReview, 'download' => $canViewReport && $report->status === MatchReportStatus::Approved],
        ];
    }

    private function teamData(GameMatch $match, $participation): array
    {
        $lineup = $match->lineups->first(fn ($item) => $item->team_participation_id === $participation->id && $item->status->value === 'submitted');
        $players = $lineup
            ? $lineup->players->map(fn ($selection): array => [
                'id' => $selection->player_registration_id,
                'name' => $selection->registration->player->full_name,
                'jersey_number' => $selection->jersey_number,
                'position' => $selection->position,
                'lineup_role' => $selection->role->value,
                'is_captain' => $selection->is_captain,
                'display_order' => $selection->display_order,
            ])
            : $participation->playerRegistrations->map(fn ($registration): array => [
                'id' => $registration->id,
                'name' => $registration->player->full_name,
                'jersey_number' => $registration->jersey_number,
                'position' => $registration->player->position,
                'lineup_role' => null,
                'is_captain' => false,
                'display_order' => null,
            ]);

        $players = $players->sortBy(fn (array $player): string => sprintf(
            '%d-%04d-%04d',
            match ($player['lineup_role']) { 'starter' => 0, 'substitute' => 1, default => 2 },
            $player['display_order'] ?? 9999,
            $player['jersey_number'] ?? 9999,
        ))->values();

        return [
            'id' => $participation->id,
            'name' => $participation->registered_name,
            'team' => $participation->team,
            'has_submitted_lineup' => (bool) $lineup,
            'players' => $players,
        ];
    }

    private function reportData(MatchReport $report): array
    {
        return ['id' => $report->id, 'folio' => $report->folio, 'status' => $report->status->value, 'home_score' => $report->home_score, 'away_score' => $report->away_score, 'home_no_show' => $report->home_no_show, 'away_no_show' => $report->away_no_show, 'summary' => $report->summary, 'incidents' => $report->incidents, 'submitted_at' => $report->submitted_at?->toIso8601String(), 'review_notes' => $report->review_notes, 'approved_at' => $report->approved_at?->toIso8601String(), 'events' => $report->events->map(fn ($event) => ['id' => $event->id, 'team_participation_id' => $event->team_participation_id, 'team_name' => $event->teamParticipation?->registered_name, 'player_registration_id' => $event->player_registration_id, 'player_name' => $event->playerRegistration?->player?->full_name, 'related_player_registration_id' => $event->related_player_registration_id, 'related_player_name' => $event->relatedPlayerRegistration?->player?->full_name, 'type' => $event->type, 'minute' => $event->minute, 'period' => $event->period, 'notes' => $event->notes])->values(), 'confirmations' => $report->confirmations->map(fn ($item) => ['team' => $item->teamParticipation->registered_name, 'representative' => $item->representative?->name, 'decision' => $item->decision, 'notes' => $item->notes, 'decided_at' => $item->decided_at?->toIso8601String()])->values()];
    }

    private function matchRelations(): array
    {
        return ['competition:id,name', 'matchday:id,name,number', 'field.venue:id,name', 'refereeAssignments:id,match_id,referee_id,role', 'refereeAssignments.referee:id,user_id', 'refereeAssignments.referee.user:id,name', 'homeParticipation.team:id,name,short_name', 'awayParticipation.team:id,name,short_name', 'homeParticipation.playerRegistrations' => fn ($query) => $query->where('status', 'active')->whereHas('player', fn ($player) => $player->where('status', 'active'))->with('player:id,full_name,position,status')->orderBy('jersey_number'), 'awayParticipation.playerRegistrations' => fn ($query) => $query->where('status', 'active')->whereHas('player', fn ($player) => $player->where('status', 'active'))->with('player:id,full_name,position,status')->orderBy('jersey_number'), 'lineups.players' => fn ($query) => $query->with('registration.player:id,full_name')->orderBy('display_order'), 'report.events.playerRegistration.player:id,full_name', 'report.events.relatedPlayerRegistration.player:id,full_name', 'report.events.teamParticipation:id,registered_name', 'report.confirmations.representative:id,name', 'report.confirmations.teamParticipation:id,registered_name'];
    }

    private function authorizeEdit(Request $request, GameMatch $match, League $league): void
    {
        $this->reports->assertCaptureAvailable($match);
        if ($this->hasPermission($request, $league, 'reports.review')) return;
        $this->authorizeCentral($request, $match, $league);
    }
    private function authorizeCentral(Request $request, GameMatch $match, League $league): void
    {
        $this->reports->assertCaptureAvailable($match);
        abort_unless($this->hasPermission($request, $league, 'reports.manage') && $match->refereeAssignments()->where('role', 'central')->whereHas('referee', fn ($query) => $query->where('user_id', $request->user()->id))->exists(), 403);
    }
    private function authorizeView(Request $request, GameMatch $match, League $league): void
    {
        if ($this->hasPermission($request, $league, 'reports.review')) return;
        $assigned = $match->refereeAssignments()->whereHas('referee', fn ($query) => $query->where('user_id', $request->user()->id))->exists();
        $owned = TeamRepresentative::where('user_id', $request->user()->id)->where('status', 'active')->whereIn('team_id', [$match->homeParticipation->team_id, $match->awayParticipation->team_id])->exists();
        abort_unless($assigned || $owned, 403);
    }
    private function assertMatch(GameMatch $match, League $league): void { $match->loadMissing('competition.tournament.season', 'homeParticipation', 'awayParticipation'); abort_unless($match->competition->tournament->season->league_id === $league->id, 404); }
    private function assertReport(MatchReport $report, League $league): void { $report->loadMissing('match.competition.tournament.season'); abort_unless($report->match->competition->tournament->season->league_id === $league->id, 404); }
    private function hasPermission(Request $request, League $league, string $permission): bool { $roleId = (int) $request->session()->get(LeagueContext::ROLE_KEY); return $request->user()->hasPermission($permission) || $request->user()->hasLeaguePermission($permission, $league->id, $roleId); }
    private function league(Request $request): League { return $this->context->current($request)['league']; }
}
