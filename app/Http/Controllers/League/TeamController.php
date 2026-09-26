<?php

namespace App\Http\Controllers\League;

use App\Domain\Competition\Models\Competition;
use App\Domain\Identity\Models\Role;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Domain\Team\Enums\ParticipationStatus;
use App\Domain\Team\Enums\TeamStatus;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamChangeRequest;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTeamRepresentativeRequest;
use App\Http\Requests\ReviewTeamChangeRequest;
use App\Http\Requests\StoreTeamChangeRequest;
use App\Http\Requests\StoreTeamParticipationRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\TransitionTeamParticipationRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TeamController extends Controller
{
    private const TEAM_FIELDS = [
        'name', 'short_name', 'primary_color', 'secondary_color', 'phone',
        'email', 'founded_on', 'description',
    ];

    public function __construct(
        private readonly LeagueContext $context,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $context = $this->context->current($request);
        $league = $context['league'];
        $canManage = $this->canManage($request);
        $teams = Team::query()->where('league_id', $league->id)
            ->when(! $canManage, fn (Builder $query) => $query->whereHas(
                'activeRepresentative', fn (Builder $representative) => $representative->where('user_id', $request->user()->id)
            ))
            ->with([
                'activeRepresentative.user:id,name,email,phone',
                'names:id,team_id,name,short_name,valid_from,valid_until,reason',
                'participations' => fn ($query) => $query->with([
                    'competition:id,name,tournament_id,division_id,category_id',
                    'competition.tournament:id,name,season_id',
                    'competition.tournament.season:id,name',
                    'competition.division:id,name',
                    'competition.category:id,name',
                ])->latest('requested_at'),
                'changeRequests' => fn ($query) => $query->where('status', 'pending')->with('requester:id,name')->latest(),
            ])
            ->orderBy('name')->get()
            ->each(function (Team $team): void {
                $team->crest_url = $team->crest_path ? Storage::disk('public')->url($team->crest_path) : null;
                $team->photo_url = $team->photo_path ? Storage::disk('public')->url($team->photo_path) : null;
            });

        return Inertia::render('League/Teams/Index', [
            'teams' => $teams,
            'canManage' => $canManage,
            'representatives' => $canManage ? $this->availableRepresentatives($league) : [],
            'competitions' => $canManage ? $this->availableCompetitions($league) : [],
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $competition = $this->competition($league, $request->integer('competition_id'));
        $membership = $this->representativeMembership($league, $request->integer('league_membership_id'));
        $this->assertRepresentativeAvailable($membership);
        $slug = Str::slug($request->string('name')->toString());
        $this->assertUniqueTeamName($league, $slug);
        $this->assertParticipationNameAvailable($competition, $request->string('name')->toString());
        $stored = ['public' => [], 'local' => []];

        try {
            DB::transaction(function () use ($request, $league, $competition, $membership, $slug, &$stored): void {
                $team = Team::create([
                    ...$request->safe()->only(self::TEAM_FIELDS),
                    'league_id' => $league->id,
                    'slug' => $slug,
                    'status' => TeamStatus::Pending,
                    'created_by' => $request->user()->id,
                ]);
                $media = $this->storeTeamMedia($request, $team, $stored);
                if ($media) $team->update($media);

                $team->names()->create([
                    'name' => $team->name, 'short_name' => $team->short_name,
                    'valid_from' => now(), 'changed_by' => $request->user()->id,
                    'reason' => $request->string('reason')->toString(),
                ]);

                $representativePhoto = $this->storePrivate($request->file('representative_photo'), "teams/{$team->id}/representatives", $stored);
                $representativeIne = $this->storePrivate($request->file('representative_ine'), "teams/{$team->id}/representatives", $stored);
                $representative = $team->representatives()->create([
                    'league_membership_id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'photo_path' => $representativePhoto,
                    'ine_path' => $representativeIne,
                    'status' => 'active', 'started_at' => now(),
                    'assigned_by' => $request->user()->id,
                ]);

                $season = $competition->tournament->season;
                $participation = $team->participations()->create([
                    'competition_id' => $competition->id,
                    'season_id' => $season->id,
                    'division_id' => $competition->division_id,
                    'registered_name' => $team->name,
                    'status' => ParticipationStatus::Pending,
                    'requested_at' => now(), 'requested_by' => $request->user()->id,
                ]);

                $this->audit->log($request, 'team.created', $team, $league, [], $team->toArray(), $request->string('reason')->toString());
                $this->audit->log($request, 'team.representative.assigned', $representative, $league, [], $representative->only(['team_id', 'user_id', 'status']), $request->string('reason')->toString());
                $this->audit->log($request, 'team.participation.requested', $participation, $league, [], $participation->toArray(), $request->string('reason')->toString());
            });
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return back()->with('success', 'Equipo registrado. Su participación quedó pendiente de aprobación.');
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertTeam($team, $league);
        $slug = Str::slug($request->string('name')->toString());
        $this->assertUniqueTeamName($league, $slug, $team->id);
        $stored = ['public' => [], 'local' => []];

        try {
            DB::transaction(function () use ($request, $team, $league, $slug, &$stored): void {
                $old = $team->toArray();
                $data = [...$request->safe()->only(self::TEAM_FIELDS), 'slug' => $slug];
                $media = $this->storeTeamMedia($request, $team, $stored);
                $this->applyTeamChanges($team, [...$data, ...$media], $request->user()->id, $request->string('reason')->toString());
                $this->audit->log($request, 'team.updated', $team, $league, $old, $team->fresh()->toArray(), $request->string('reason')->toString());
            });
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return back()->with('success', 'Información del equipo actualizada.');
    }

    public function proposeChange(StoreTeamChangeRequest $request, Team $team): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertOwnedTeam($team, $league, $request->user()->id);
        if ($team->changeRequests()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['request_reason' => 'Ya existe una solicitud pendiente para este equipo.']);
        }
        $slug = Str::slug($request->string('name')->toString());
        $this->assertUniqueTeamName($league, $slug, $team->id);
        $stored = ['public' => [], 'local' => []];

        try {
            $changes = [...$request->safe()->only(self::TEAM_FIELDS), 'slug' => $slug];
            if ($request->hasFile('crest')) $changes['crest_pending_path'] = $this->storePrivate($request->file('crest'), "team-change-requests/{$team->id}", $stored);
            if ($request->hasFile('team_photo')) $changes['photo_pending_path'] = $this->storePrivate($request->file('team_photo'), "team-change-requests/{$team->id}", $stored);
            $change = $team->changeRequests()->create([
                'requested_by' => $request->user()->id,
                'changes' => $changes,
                'status' => 'pending',
                'request_reason' => $request->string('request_reason')->toString(),
            ]);
            $this->audit->log($request, 'team.change_requested', $change, $league, [], $change->toArray(), $change->request_reason);
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return back()->with('success', 'Cambio enviado para aprobación del administrador.');
    }

    public function reviewChange(ReviewTeamChangeRequest $request, TeamChangeRequest $change): RedirectResponse
    {
        $league = $this->league($request);
        $change->load('team');
        $this->assertTeam($change->team, $league);
        if ($change->status !== 'pending') throw ValidationException::withMessages(['decision' => 'Esta solicitud ya fue revisada.']);
        $decision = $request->validated('decision');

        DB::transaction(function () use ($request, $change, $league, $decision): void {
            if ($decision === 'approve') {
                $changes = $change->changes;
                $this->assertUniqueTeamName($league, $changes['slug'], $change->team_id);
                $changes = $this->publishPendingMedia($change->team, $changes);
                $this->applyTeamChanges($change->team, $changes, $request->user()->id, $request->string('reason')->toString());
            } else {
                $this->deletePendingMedia($change->changes);
            }
            $change->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_reason' => $request->string('reason')->toString(),
            ]);
            $this->audit->log($request, "team.change_{$change->status}", $change, $league, [], $change->fresh()->toArray(), $request->string('reason')->toString());
        });

        return back()->with('success', $decision === 'approve' ? 'Cambio aprobado y aplicado.' : 'Cambio rechazado.');
    }

    public function assignRepresentative(AssignTeamRepresentativeRequest $request, Team $team): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertTeam($team, $league);
        $membership = $this->representativeMembership($league, $request->integer('league_membership_id'));
        $this->assertRepresentativeAvailable($membership, $team->id);
        $stored = ['public' => [], 'local' => []];

        try {
            DB::transaction(function () use ($request, $team, $membership, $league, &$stored): void {
                $team->representatives()->where('status', 'active')->update(['status' => 'ended', 'ended_at' => now()]);
                $representative = $team->representatives()->create([
                    'league_membership_id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'photo_path' => $this->storePrivate($request->file('representative_photo'), "teams/{$team->id}/representatives", $stored),
                    'ine_path' => $this->storePrivate($request->file('representative_ine'), "teams/{$team->id}/representatives", $stored),
                    'status' => 'active', 'started_at' => now(), 'assigned_by' => $request->user()->id,
                ]);
                $this->audit->log($request, 'team.representative.reassigned', $representative, $league, [], $representative->only(['team_id', 'user_id', 'status']), $request->string('reason')->toString());
            });
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return back()->with('success', 'Representante actualizado correctamente.');
    }

    public function transitionParticipation(TransitionTeamParticipationRequest $request, TeamParticipation $participation): RedirectResponse
    {
        $league = $this->league($request);
        $participation->load('team');
        $this->assertTeam($participation->team, $league);
        $action = $request->validated('action');
        $current = $participation->status->value;
        $transitions = [
            'pending' => ['approve' => 'active', 'reject' => 'rejected'],
            'rejected' => ['approve' => 'active', 'deregister' => 'deregistered'],
            'active' => ['suspend' => 'suspended', 'deactivate' => 'inactive', 'deregister' => 'deregistered'],
            'suspended' => ['reactivate' => 'active', 'deactivate' => 'inactive', 'deregister' => 'deregistered'],
            'inactive' => ['reactivate' => 'active', 'deregister' => 'deregistered'],
            'deregistered' => [],
        ];
        $target = $transitions[$current][$action] ?? null;
        if (! $target) throw ValidationException::withMessages(['action' => 'Esta transición no está permitida.']);

        DB::transaction(function () use ($request, $participation, $league, $current, $target): void {
            $participation->update([
                'status' => ParticipationStatus::from($target),
                'reviewed_at' => now(), 'reviewed_by' => $request->user()->id,
                'review_reason' => $request->string('reason')->toString(),
                'suspended_at' => $target === 'suspended' ? now() : $participation->suspended_at,
                'reactivated_at' => $target === 'active' && $current !== 'pending' ? now() : $participation->reactivated_at,
            ]);
            $teamStatus = match ($target) {
                'active' => TeamStatus::Active,
                'suspended' => TeamStatus::Suspended,
                'deregistered' => TeamStatus::Deregistered,
                default => TeamStatus::Inactive,
            };
            $participation->team->update(['status' => $teamStatus]);
            $this->audit->log($request, 'team.participation.status_changed', $participation, $league, ['status' => $current], ['status' => $target], $request->string('reason')->toString());
        });

        return back()->with('success', 'Estado de la participación actualizado.');
    }

    public function requestParticipation(StoreTeamParticipationRequest $request, Team $team): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertTeam($team, $league);
        $competition = $this->competition($league, $request->integer('competition_id'));
        $season = $competition->tournament->season;
        if ($team->participations()->where('season_id', $season->id)->exists()) {
            throw ValidationException::withMessages(['competition_id' => 'El equipo ya tiene una participación en esta temporada.']);
        }
        $this->assertParticipationNameAvailable($competition, $team->name);

        $participation = $team->participations()->create([
            'competition_id' => $competition->id,
            'season_id' => $season->id,
            'division_id' => $competition->division_id,
            'registered_name' => $team->name,
            'status' => ParticipationStatus::Pending,
            'requested_at' => now(),
            'requested_by' => $request->user()->id,
        ]);
        $team->update(['status' => TeamStatus::Pending]);
        $this->audit->log($request, 'team.participation.requested', $participation, $league, [], $participation->toArray(), $request->string('reason')->toString());

        return back()->with('success', 'Participación registrada y pendiente de aprobación.');
    }

    public function representativeDocument(Request $request, Team $team, string $document): StreamedResponse
    {
        $league = $this->league($request);
        $this->assertTeam($team, $league);
        abort_unless(in_array($document, ['photo', 'ine'], true), 404);
        $representative = $team->activeRepresentative()->firstOrFail();
        $path = $document === 'photo' ? $representative->photo_path : $representative->ine_path;
        abort_unless(Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path);
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }

    private function canManage(Request $request): bool
    {
        $context = $this->context->current($request);
        return $request->user()->hasPermission('teams.manage')
            || $request->user()->hasLeaguePermission('teams.manage', $context['league']->id, $context['role']->id);
    }

    private function availableRepresentatives(League $league)
    {
        return LeagueMembership::where('league_id', $league->id)->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'team_representative'))
            ->whereDoesntHave('user.teamRepresentations', fn ($query) => $query->where('status', 'active'))
            ->with('user:id,name,email')->get(['id', 'user_id']);
    }

    private function availableCompetitions(League $league)
    {
        return Competition::whereHas('tournament.season', fn ($query) => $query->where('league_id', $league->id)->whereNotIn('status', ['archived', 'cancelled']))
            ->with(['tournament:id,name,season_id', 'tournament.season:id,name', 'division:id,name', 'category:id,name'])
            ->orderBy('name')->get();
    }

    private function competition(League $league, int $id): Competition
    {
        return Competition::whereHas('tournament.season', fn ($query) => $query->where('league_id', $league->id)->whereNotIn('status', ['archived', 'cancelled']))
            ->with('tournament.season')->findOrFail($id);
    }

    private function representativeMembership(League $league, int $id): LeagueMembership
    {
        return LeagueMembership::where('league_id', $league->id)->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'team_representative'))
            ->findOrFail($id);
    }

    private function assertRepresentativeAvailable(LeagueMembership $membership, ?int $exceptTeamId = null): void
    {
        $assigned = TeamRepresentative::where('user_id', $membership->user_id)->where('status', 'active')
            ->when($exceptTeamId, fn ($query) => $query->where('team_id', '!=', $exceptTeamId))->exists();
        if ($assigned) throw ValidationException::withMessages(['league_membership_id' => 'Este representante ya administra otro equipo.']);
    }

    private function assertUniqueTeamName(League $league, string $slug, ?int $exceptTeamId = null): void
    {
        $exists = Team::where('league_id', $league->id)->where('slug', $slug)
            ->when($exceptTeamId, fn ($query) => $query->whereKeyNot($exceptTeamId))->exists();
        if ($exists) throw ValidationException::withMessages(['name' => 'Ya existe un equipo con este nombre en la liga.']);
    }

    private function assertParticipationNameAvailable(Competition $competition, string $name): void
    {
        $exists = TeamParticipation::where('season_id', $competition->tournament->season_id)
            ->where('division_id', $competition->division_id)
            ->whereRaw('LOWER(registered_name) = ?', [Str::lower($name)])->exists();
        if ($exists) throw ValidationException::withMessages(['name' => 'Ya existe un equipo con este nombre en la temporada y división.']);
    }

    private function assertTeam(Team $team, League $league): void { abort_unless($team->league_id === $league->id, 404); }

    private function assertOwnedTeam(Team $team, League $league, int $userId): void
    {
        $this->assertTeam($team, $league);
        abort_unless($team->activeRepresentative()->where('user_id', $userId)->exists(), 403);
    }

    private function storeTeamMedia(Request $request, Team $team, array &$stored): array
    {
        $media = [];
        if ($request->hasFile('crest')) $media['crest_path'] = $this->storePublic($request->file('crest'), "teams/{$team->id}", $stored);
        if ($request->hasFile('team_photo')) $media['photo_path'] = $this->storePublic($request->file('team_photo'), "teams/{$team->id}", $stored);
        return $media;
    }

    private function storePublic(?UploadedFile $file, string $directory, array &$stored): string
    {
        $path = $file->store($directory, 'public');
        if (! $path) throw ValidationException::withMessages(['file' => 'No fue posible guardar la imagen.']);
        $stored['public'][] = $path;
        return $path;
    }

    private function storePrivate(?UploadedFile $file, string $directory, array &$stored): string
    {
        $path = $file->store($directory, 'local');
        if (! $path) throw ValidationException::withMessages(['file' => 'No fue posible guardar el documento privado.']);
        $stored['local'][] = $path;
        return $path;
    }

    private function deleteStored(array $stored): void
    {
        Storage::disk('public')->delete($stored['public']);
        Storage::disk('local')->delete($stored['local']);
    }

    private function applyTeamChanges(Team $team, array $changes, int $userId, string $reason): void
    {
        $nameChanged = ($changes['name'] ?? $team->name) !== $team->name || ($changes['short_name'] ?? $team->short_name) !== $team->short_name;
        if ($nameChanged) {
            $team->names()->whereNull('valid_until')->update(['valid_until' => now()]);
            $team->names()->create([
                'name' => $changes['name'], 'short_name' => $changes['short_name'],
                'valid_from' => now(), 'changed_by' => $userId, 'reason' => $reason,
            ]);
        }
        $team->update($changes);
    }

    private function publishPendingMedia(Team $team, array $changes): array
    {
        foreach (['crest' => 'crest_path', 'photo' => 'photo_path'] as $prefix => $target) {
            $pendingKey = "{$prefix}_pending_path";
            if (! isset($changes[$pendingKey])) continue;
            $source = $changes[$pendingKey];
            $extension = pathinfo($source, PATHINFO_EXTENSION);
            $destination = "teams/{$team->id}/".Str::uuid().($extension ? ".{$extension}" : '');
            $stream = Storage::disk('local')->readStream($source);
            if (! $stream || ! Storage::disk('public')->writeStream($destination, $stream)) {
                if (is_resource($stream)) fclose($stream);
                throw ValidationException::withMessages(['decision' => 'No fue posible publicar la imagen pendiente.']);
            }
            if (is_resource($stream)) fclose($stream);
            Storage::disk('local')->delete($source);
            unset($changes[$pendingKey]);
            $changes[$target] = $destination;
        }
        return $changes;
    }

    private function deletePendingMedia(array $changes): void
    {
        Storage::disk('local')->delete(array_filter([
            $changes['crest_pending_path'] ?? null,
            $changes['photo_pending_path'] ?? null,
        ]));
    }
}
