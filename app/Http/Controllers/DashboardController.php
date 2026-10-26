<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Notification\Enums\NoticeStatus;
use App\Domain\Notification\Models\LeagueNoticeRecipient;
use App\Domain\Player\Enums\RegistrationStatus;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Scheduling\Enums\MatchReportStatus;
use App\Domain\Scheduling\Enums\MatchStatus;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Team\Enums\ParticipationStatus;
use App\Domain\Team\Enums\TeamStatus;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Support\LeagueContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $activeContext = app(LeagueContext::class)->current($request);
        $league = $activeContext['league'] ?? null;
        $user = $request->user();
        $roleId = $activeContext['role']->id ?? null;
        $hasPermission = fn (string $permission): bool => $user->hasPermission($permission)
            || ($league && $roleId && $user->hasLeaguePermission($permission, $league->id, $roleId));

        $unread = $activeContext ? LeagueNoticeRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereHas('notice', fn ($query) => $query
                ->where('league_id', $league->id)
                ->where('status', NoticeStatus::Published)
                ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->count() : 0;

        $pendingTasks = 0;
        $upcomingMatches = collect();
        $activeTeams = 0;
        $recentActivity = collect();

        if ($league) {
            if ($hasPermission('players.manage')) {
                $pendingTasks += PlayerRegistration::query()
                    ->where('status', RegistrationStatus::Pending->value)
                    ->whereHas('team', fn ($query) => $query->where('league_id', $league->id))
                    ->count();
            }

            if ($hasPermission('teams.manage')) {
                $pendingTasks += TeamParticipation::query()
                    ->where('status', ParticipationStatus::Pending->value)
                    ->whereHas('team', fn ($query) => $query->where('league_id', $league->id))
                    ->count();
            }

            if ($hasPermission('reports.review')) {
                $pendingTasks += MatchReport::query()
                    ->where('status', MatchReportStatus::UnderReview->value)
                    ->whereHas('match.competition.tournament.season', fn ($query) => $query->where('league_id', $league->id))
                    ->count();
            }

            $activeTeams = Team::query()
                ->where('league_id', $league->id)
                ->where('status', TeamStatus::Active->value)
                ->count();

            if ($hasPermission('schedule.view')) {
                $upcomingMatches = GameMatch::query()
                    ->where('scheduled_at', '>=', now())
                    ->whereIn('status', [MatchStatus::Scheduled->value, MatchStatus::InProgress->value])
                    ->whereHas('competition.tournament.season', fn ($query) => $query->where('league_id', $league->id))
                    ->with([
                        'competition:id,name,tournament_id',
                        'matchday:id,name,number',
                        'homeParticipation:id,team_id,registered_name',
                        'awayParticipation:id,team_id,registered_name',
                        'homeParticipation.team:id,name,crest_path',
                        'awayParticipation.team:id,name,crest_path',
                        'field:id,name',
                    ])
                    ->orderBy('scheduled_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (GameMatch $match) => [
                        'id' => $match->id,
                        'competition' => $match->competition?->name,
                        'matchday' => $match->matchday?->name ?: ($match->matchday ? 'Jornada '.$match->matchday->number : 'Por definir'),
                        'homeTeam' => $match->homeParticipation?->team?->name ?? $match->homeParticipation?->registered_name ?? 'Local',
                        'awayTeam' => $match->awayParticipation?->team?->name ?? $match->awayParticipation?->registered_name ?? 'Visitante',
                        'scheduledAt' => $match->scheduled_at?->toIso8601String(),
                        'field' => $match->field?->name ?? 'Campo por definir',
                    ]);
            }

            $recentActivity = AuditLog::query()
                ->where('league_id', $league->id)
                ->when(! $hasPermission('audit.view'), fn ($query) => $query->where('actor_user_id', $user->id))
                ->with('actor:id,name')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $entry) => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'actor' => $entry->actor?->name ?? 'Sistema',
                    'createdAt' => $entry->created_at?->toIso8601String(),
                ]);
        }

        return Inertia::render('Dashboard', [
            'summary' => [
                'pendingTasks' => $pendingTasks,
                'upcomingMatches' => $upcomingMatches->count(),
                'notifications' => $unread,
                'activeTeams' => $activeTeams,
            ],
            'upcomingMatches' => $upcomingMatches,
            'recentActivity' => $recentActivity,
        ]);
    }
}
