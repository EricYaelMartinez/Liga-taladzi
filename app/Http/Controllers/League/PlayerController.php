<?php

namespace App\Http\Controllers\League;

use App\Domain\League\Models\League;
use App\Domain\Player\Enums\PlayerStatus;
use App\Domain\Player\Enums\RegistrationStatus;
use App\Domain\Player\Models\Player;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Team\Models\TeamParticipation;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkPlayerUserRequest;
use App\Http\Requests\StorePlayerRegistrationRequest;
use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\TransitionPlayerRegistrationRequest;
use App\Http\Requests\UpdatePlayerProfileRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Carbon\Carbon;
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

class PlayerController extends Controller
{
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
        $isRepresentative = $context['role']->slug === 'team_representative';
        $isPlayer = $context['role']->slug === 'player';

        $players = Player::query()->where('league_id', $league->id)
            ->when($isRepresentative, fn (Builder $query) => $query->whereHas(
                'registrations.team.activeRepresentative', fn (Builder $representative) => $representative->where('user_id', $request->user()->id)
            ))
            ->when($isPlayer, fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->with([
                'user:id,name,email',
                'documents:id,player_id,type,retain_until',
                'registrations' => fn ($query) => $query->with([
                    'team:id,name,short_name',
                    'teamParticipation.competition.tournament.season:id,name,starts_on,ends_on',
                    'teamParticipation.competition.division:id,name',
                    'teamParticipation.competition.category:id,name',
                ])->latest('requested_at'),
                'movements' => fn ($query) => $query->with(['fromTeam:id,name', 'toTeam:id,name'])->latest('occurred_at'),
            ])->orderBy('full_name')->get();

        if (! $canManage && ! $isPlayer) {
            $players->each->makeHidden([
                'birth_date', 'phone', 'email', 'emergency_contact_name', 'emergency_contact_phone',
                'emergency_contact_relationship', 'guardian_name', 'guardian_phone', 'user_id',
            ]);
        }

        return Inertia::render('League/Players/Index', [
            'players' => $players,
            'canManage' => $canManage,
            'isRepresentative' => $isRepresentative,
            'isPlayer' => $isPlayer,
            'teamParticipations' => $isPlayer ? [] : $this->availableParticipations($league, $request),
            'playerMemberships' => $canManage ? $this->availablePlayerMemberships($league) : [],
            'availablePlayers' => $isPlayer ? [] : $this->availablePlayers($league),
            'credentialTeams' => $canManage ? $this->credentialParticipations($league) : [],
            'credentialLogosConfigured' => collect(range(1, 4))
                ->filter(fn (int $position) => filled($league->{"credential_logo_{$position}_path"}))
                ->count(),
        ]);
    }

    public function store(StorePlayerRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $participation = $this->participation($league, $request, $request->integer('team_participation_id'));
        $this->assertRosterEligibility($request->date('birth_date'), $request->string('gender')->toString(), $participation);
        $this->assertRosterSpace($participation);
        $this->assertJerseyAvailable($participation, $request->integer('jersey_number'));
        $photo = $request->file('photo');
        $hash = hash_file('sha256', $photo->getRealPath());
        $this->assertNotDuplicate($league, $request->string('full_name')->toString(), $request->date('birth_date'), $hash);
        $membership = $request->filled('league_membership_id')
            ? $this->playerMembership($league, $request->integer('league_membership_id'))
            : null;
        $stored = [];

        try {
            DB::transaction(function () use ($request, $league, $participation, $membership, $photo, $hash, &$stored): void {
                $photoPath = $this->storePrivate($photo, 'players/photos', $stored);
                $player = Player::create([
                    ...$request->safe()->only([
                        'full_name', 'birth_date', 'gender', 'position', 'phone', 'email',
                        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
                        'guardian_name', 'guardian_phone',
                    ]),
                    'league_id' => $league->id,
                    'user_id' => $membership?->user_id,
                    'photo_path' => $photoPath,
                    'photo_hash' => $hash,
                    'status' => PlayerStatus::Pending,
                    'created_by' => $request->user()->id,
                ]);

                if ($request->hasFile('guardian_consent')) {
                    $documentPath = $this->storePrivate($request->file('guardian_consent'), "players/{$player->id}/documents", $stored);
                    $player->documents()->create([
                        'type' => 'guardian_consent', 'path' => $documentPath,
                        'retain_until' => $participation->season->ends_on,
                        'uploaded_by' => $request->user()->id,
                    ]);
                }

                $registration = $this->createRegistration($request, $player, $participation, $request->integer('jersey_number'), $request->string('reason')->toString());
                $this->audit->log($request, 'player.created', $player, $league, [], $player->only(['id', 'full_name', 'birth_date', 'status']), $request->string('reason')->toString());
                $this->audit->log($request, 'player.registration.requested', $registration, $league, [], $registration->toArray(), $request->string('reason')->toString());
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }

        return back()->with('success', 'Jugador registrado. El alta quedó pendiente de aprobación.');
    }

    public function registerExisting(StorePlayerRegistrationRequest $request, Player $player): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertPlayer($player, $league);
        $participation = $this->participation($league, $request, $request->integer('team_participation_id'));
        $this->assertRosterEligibility($player->birth_date, $player->gender, $participation);
        $this->assertRosterSpace($participation);
        $this->assertJerseyAvailable($participation, $request->integer('jersey_number'));
        $this->assertPlayerAvailableForSeason($player, $participation->season_id);

        $registration = $this->createRegistration($request, $player, $participation, $request->integer('jersey_number'), $request->string('reason')->toString());
        $player->update(['status' => PlayerStatus::Pending]);
        $this->audit->log($request, 'player.registration.requested', $registration, $league, [], $registration->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Alta solicitada para el jugador existente.');
    }

    public function transition(TransitionPlayerRegistrationRequest $request, PlayerRegistration $registration): RedirectResponse
    {
        $league = $this->league($request);
        $registration->load(['player', 'team']);
        $this->assertPlayer($registration->player, $league);
        $action = $request->validated('action');
        $current = $registration->status->value;
        $target = [
            'pending' => ['approve' => 'active', 'reject' => 'rejected'],
            'active' => ['suspend' => 'suspended', 'release' => 'released'],
            'suspended' => ['reactivate' => 'active', 'release' => 'released'],
            'rejected' => [], 'released' => [],
        ][$current][$action] ?? null;
        if (! $target) throw ValidationException::withMessages(['action' => 'Esta transición no está permitida.']);

        DB::transaction(function () use ($request, $registration, $league, $current, $target): void {
            $this->applyTransition($request, $registration, $target, $request->string('reason')->toString());
            $this->audit->log($request, 'player.registration.status_changed', $registration, $league, ['status' => $current], ['status' => $target], $request->string('reason')->toString());
        });
        return back()->with('success', 'Estado del jugador actualizado.');
    }

    public function release(Request $request, PlayerRegistration $registration): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $league = $this->league($request);
        $registration->load(['player', 'team']);
        $this->assertPlayer($registration->player, $league);
        $allowed = $this->canManage($request)
            || $registration->team->activeRepresentative()->where('user_id', $request->user()->id)->exists()
            || $registration->player->user_id === $request->user()->id;
        abort_unless($allowed, 403);
        abort_unless(in_array($registration->status->value, ['active', 'suspended'], true), 422);

        DB::transaction(function () use ($request, $registration, $league, $data): void {
            $this->applyTransition($request, $registration, 'released', $data['reason']);
            $this->audit->log($request, 'player.registration.released', $registration, $league, [], ['status' => 'released'], $data['reason']);
        });
        return back()->with('success', 'La baja fue registrada; el jugador puede incorporarse a otro equipo.');
    }

    public function linkUser(LinkPlayerUserRequest $request, Player $player): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertPlayer($player, $league);
        $membership = $this->playerMembership($league, $request->integer('league_membership_id'));
        $old = $player->user_id;
        $player->update(['user_id' => $membership->user_id]);
        $this->audit->log($request, 'player.user_linked', $player, $league, ['user_id' => $old], ['user_id' => $membership->user_id], $request->string('reason')->toString());
        return back()->with('success', 'Cuenta vinculada al jugador.');
    }

    public function updateProfile(UpdatePlayerProfileRequest $request, Player $player): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertPlayer($player, $league);
        abort_unless($this->canManage($request) || $player->user_id === $request->user()->id, 403);
        $old = $player->only(['phone', 'email', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship']);
        $player->update($request->safe()->only(array_keys($old)));
        $this->audit->log($request, 'player.contact_updated', $player, $league, $old, $player->only(array_keys($old)), $request->string('reason')->toString());
        return back()->with('success', 'Datos de contacto actualizados.');
    }

    public function privateFile(Request $request, Player $player, string $document): StreamedResponse
    {
        $league = $this->league($request);
        $this->assertPlayer($player, $league);
        $path = match ($document) {
            'photo' => $player->getRawOriginal('photo_path'),
            'guardian-consent' => $player->documents()->where('type', 'guardian_consent')->latest()->value('path'),
            default => null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path);
    }

    private function createRegistration(Request $request, Player $player, TeamParticipation $participation, int $jersey, string $reason): PlayerRegistration
    {
        $previous = $player->registrations()->where('season_id', $participation->season_id)->where('status', 'released')->latest('released_at')->first();
        $registration = $player->registrations()->create([
            'team_id' => $participation->team_id, 'team_participation_id' => $participation->id,
            'competition_id' => $participation->competition_id, 'season_id' => $participation->season_id,
            'division_id' => $participation->division_id, 'jersey_number' => $jersey,
            'status' => RegistrationStatus::Pending, 'requested_at' => now(), 'requested_by' => $request->user()->id,
        ]);
        $player->movements()->create([
            'player_registration_id' => $registration->id,
            'from_team_id' => $previous?->team_id, 'to_team_id' => $participation->team_id,
            'type' => $previous ? 'transferred' : 'registration_requested',
            'reason' => $reason, 'performed_by' => $request->user()->id, 'occurred_at' => now(),
        ]);
        return $registration;
    }

    private function applyTransition(Request $request, PlayerRegistration $registration, string $target, string $reason): void
    {
        $previousStatus = $registration->status->value;
        $registration->update([
            'status' => RegistrationStatus::from($target), 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id,
            'review_reason' => $reason, 'released_at' => $target === 'released' ? now() : $registration->released_at,
            'released_by' => $target === 'released' ? $request->user()->id : $registration->released_by,
            'release_reason' => $target === 'released' ? $reason : $registration->release_reason,
        ]);
        $registration->player->update(['status' => match ($target) {
            'active' => PlayerStatus::Active,
            'suspended' => PlayerStatus::Suspended,
            default => PlayerStatus::Inactive,
        }]);
        $registration->player->movements()->create([
            'player_registration_id' => $registration->id,
            'from_team_id' => $target === 'released' ? $registration->team_id : null,
            'to_team_id' => in_array($target, ['active', 'suspended'], true) ? $registration->team_id : null,
            'type' => match ($target) { 'active' => $previousStatus === 'suspended' ? 'reactivated' : 'approved', default => $target },
            'reason' => $reason, 'performed_by' => $request->user()->id, 'occurred_at' => now(),
        ]);
    }

    private function availableParticipations(League $league, Request $request)
    {
        return TeamParticipation::whereHas('team', fn ($query) => $query->where('league_id', $league->id))
            ->where('status', 'active')
            ->when(! $this->canManage($request), fn ($query) => $query->whereHas('team.activeRepresentative', fn ($representative) => $representative->where('user_id', $request->user()->id)))
            ->with(['team:id,name', 'competition:id,name,tournament_id,division_id,category_id', 'competition.tournament.season:id,name,starts_on,ends_on', 'competition.division:id,name', 'competition.category:id,name'])
            ->get();
    }

    private function availablePlayers(League $league)
    {
        return Player::where('league_id', $league->id)->whereDoesntHave('registrations', fn ($query) => $query->whereIn('status', ['pending', 'active', 'suspended']))
            ->orderBy('full_name')->get(['id', 'full_name', 'birth_date', 'position']);
    }

    private function credentialParticipations(League $league)
    {
        return TeamParticipation::query()
            ->whereHas('team', fn ($query) => $query->where('league_id', $league->id))
            ->where('status', 'active')
            ->with([
                'team:id,name,short_name',
                'season:id,name,starts_on,ends_on',
                'competition:id,name,tournament_id,division_id,category_id',
                'competition.division:id,name',
                'competition.category:id,name',
            ])
            ->withCount(['playerRegistrations as active_players_count' => fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('player', fn ($player) => $player->where('status', 'active'))])
            ->get()
            ->sortBy(fn (TeamParticipation $participation) => Str::lower($participation->team->name).'|'.$participation->season->starts_on)
            ->values();
    }

    private function availablePlayerMemberships(League $league)
    {
        return \App\Domain\League\Models\LeagueMembership::where('league_id', $league->id)->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'player'))
            ->whereDoesntHave('user.playerProfiles', fn ($query) => $query->where('league_id', $league->id))
            ->with('user:id,name,email')->get(['id', 'user_id']);
    }

    private function playerMembership(League $league, int $id): \App\Domain\League\Models\LeagueMembership
    {
        $membership = \App\Domain\League\Models\LeagueMembership::where('league_id', $league->id)->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'player'))->findOrFail($id);
        if (Player::where('league_id', $league->id)->where('user_id', $membership->user_id)->exists()) {
            throw ValidationException::withMessages(['league_membership_id' => 'Esta cuenta ya está vinculada a otro jugador.']);
        }
        return $membership;
    }

    private function participation(League $league, Request $request, int $id): TeamParticipation
    {
        $participation = TeamParticipation::whereHas('team', fn ($query) => $query->where('league_id', $league->id))
            ->where('status', 'active')->with(['team.activeRepresentative', 'competition.category', 'season'])->findOrFail($id);
        if (! $this->canManage($request)) {
            abort_unless($participation->team->activeRepresentative?->user_id === $request->user()->id, 403);
        }
        return $participation;
    }

    private function assertRosterEligibility(Carbon $birthDate, string $gender, TeamParticipation $participation): void
    {
        $category = $participation->competition->category;
        $age = (int) $birthDate->diffInYears(Carbon::parse($participation->season->starts_on));
        if ($category->minimum_age !== null && $age < $category->minimum_age) throw ValidationException::withMessages(['birth_date' => 'El jugador no alcanza la edad mínima de la categoría.']);
        if ($category->maximum_age !== null && $age > $category->maximum_age) throw ValidationException::withMessages(['birth_date' => 'El jugador supera la edad máxima de la categoría.']);
        if ($category->gender !== 'mixed' && $gender !== $category->gender) throw ValidationException::withMessages(['gender' => 'El género no corresponde a la categoría.']);
    }

    private function assertRosterSpace(TeamParticipation $participation): void
    {
        $count = $participation->playerRegistrations()->whereIn('status', ['pending', 'active', 'suspended'])->count();
        if ($count >= $participation->competition->maximum_roster_size) throw ValidationException::withMessages(['team_participation_id' => 'La plantilla alcanzó el máximo permitido.']);
    }

    private function assertJerseyAvailable(TeamParticipation $participation, int $jersey): void
    {
        if ($participation->playerRegistrations()->where('jersey_number', $jersey)->whereIn('status', ['pending', 'active', 'suspended'])->exists()) {
            throw ValidationException::withMessages(['jersey_number' => 'Este dorsal ya está ocupado en el equipo.']);
        }
    }

    private function assertPlayerAvailableForSeason(Player $player, int $seasonId): void
    {
        if ($player->registrations()->where('season_id', $seasonId)->whereIn('status', ['pending', 'active', 'suspended'])->exists()) {
            throw ValidationException::withMessages(['player_id' => 'El jugador ya pertenece a un equipo en esta temporada.']);
        }
    }

    private function assertNotDuplicate(League $league, string $name, Carbon $birthDate, string $hash): void
    {
        if (Player::withTrashed()->where('league_id', $league->id)->whereDate('birth_date', $birthDate)->where('photo_hash', $hash)->whereRaw('LOWER(full_name) = ?', [Str::lower($name)])->exists()) {
            throw ValidationException::withMessages(['full_name' => 'Ya existe un jugador con el mismo nombre, fecha de nacimiento y fotografía.']);
        }
    }

    private function storePrivate(?UploadedFile $file, string $directory, array &$stored): string
    {
        $path = $file?->store($directory, 'local');
        if (! $path) throw ValidationException::withMessages(['file' => 'No fue posible guardar el archivo privado.']);
        $stored[] = $path;
        return $path;
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
    private function assertPlayer(Player $player, League $league): void { abort_unless($player->league_id === $league->id, 404); }
    private function canManage(Request $request): bool
    {
        $context = $this->context->current($request);
        return $request->user()->hasPermission('players.manage') || $request->user()->hasLeaguePermission('players.manage', $context['league']->id, $context['role']->id);
    }
}
