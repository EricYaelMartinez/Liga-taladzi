<?php

namespace App\Http\Controllers\League;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Referee\Models\Referee;
use App\Domain\Referee\Models\RefereeAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefereeAvailabilityRequest;
use App\Http\Requests\StoreRefereeObservationRequest;
use App\Http\Requests\StoreRefereeRequest;
use App\Http\Requests\UpdateOwnRefereeProfileRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RefereeController extends Controller
{
    public function __construct(private readonly LeagueContext $context, private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $league = $this->league($request);
        $canManage = $this->canManage($request, $league);
        $query = Referee::where('league_id', $league->id)
            ->with([
                'user:id,name,email,phone,status',
                'availabilities' => fn ($items) => $items->orderBy('weekday')->orderBy('starts_at'),
                'observations' => fn ($items) => $items->when($canManage, fn ($query) => $query->with('creator:id,name')->latest('observed_on'))->when(! $canManage, fn ($query) => $query->whereRaw('1 = 0')),
            ])
            ->orderBy('category_level');

        if (! $canManage) $query->where('user_id', $request->user()->id);

        $referees = $query->get()->each(function (Referee $referee) use ($canManage): void {
            $referee->setAttribute('photo_url', $referee->photo_path ? Storage::disk('public')->url($referee->photo_path) : null);
            if (! $canManage) $referee->makeHidden('notes');
        });

        return Inertia::render('League/Referees/Index', [
            'referees' => $referees,
            'canManage' => $canManage,
            'eligibleMembers' => $canManage ? User::query()
                ->whereHas('leagueMemberships', fn ($memberships) => $memberships
                    ->where('league_id', $league->id)
                    ->where('status', 'active')
                    ->whereHas('role', fn ($role) => $role->where('slug', 'referee')))
                ->whereDoesntHave('refereeProfiles', fn ($profiles) => $profiles->where('league_id', $league->id))
                ->orderBy('name')->get(['id', 'name', 'email', 'phone']) : [],
        ]);
    }

    public function store(StoreRefereeRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertCanManage($request, $league);
        $this->assertEligibleUser($league, $request->integer('user_id'));
        $data = $request->safe()->except(['photo', 'reason']);
        if ($request->hasFile('photo')) $data['photo_path'] = $request->file('photo')->store("referees/{$league->id}", 'public');
        $referee = $league->referees()->create($data);
        $this->audit->log($request, 'referee.created', $referee, $league, [], $referee->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Árbitro registrado. Ya puedes configurar su disponibilidad.');
    }

    public function update(StoreRefereeRequest $request, Referee $referee): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertCanManage($request, $league);
        $this->assertReferee($referee, $league);
        if ($request->integer('user_id') !== $referee->user_id) $this->assertEligibleUser($league, $request->integer('user_id'));
        $old = $referee->toArray();
        $data = $request->safe()->except(['photo', 'reason']);
        if ($request->hasFile('photo')) {
            $oldPhoto = $referee->photo_path;
            $data['photo_path'] = $request->file('photo')->store("referees/{$league->id}", 'public');
            if ($oldPhoto) Storage::disk('public')->delete($oldPhoto);
        }
        $referee->update($data);
        $this->audit->log($request, 'referee.updated', $referee, $league, $old, $referee->fresh()->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Expediente del árbitro actualizado.');
    }

    public function updateOwn(UpdateOwnRefereeProfileRequest $request, Referee $referee): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertReferee($referee, $league);
        abort_unless($referee->user_id === $request->user()->id, 403);
        $old = $referee->only(['photo_path', 'contact_email', 'contact_phone']);
        $data = $request->safe()->except(['photo', 'reason']);
        if ($request->hasFile('photo')) {
            $oldPhoto = $referee->photo_path;
            $data['photo_path'] = $request->file('photo')->store("referees/{$league->id}", 'public');
            if ($oldPhoto) Storage::disk('public')->delete($oldPhoto);
        }
        $referee->update($data);
        $this->audit->log($request, 'referee.profile_updated', $referee, $league, $old, $referee->fresh()->only(array_keys($old)), $request->string('reason')->toString());
        return back()->with('success', 'Tus datos de contacto fueron actualizados.');
    }

    public function storeAvailability(StoreRefereeAvailabilityRequest $request, Referee $referee): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertReferee($referee, $league);
        $this->assertCanEditOwnOrManage($request, $referee, $league);
        $data = $request->safe()->except('reason');
        $availability = DB::transaction(function () use ($referee, $data): RefereeAvailability {
            Referee::whereKey($referee->id)->lockForUpdate()->firstOrFail();
            $overlap = $referee->availabilities()->where('weekday', $data['weekday'])
                ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])
                ->when($data['valid_until'] ?? null, fn ($query, $until) => $query->where(fn ($period) => $period->whereNull('valid_from')->orWhereDate('valid_from', '<=', $until)))
                ->when($data['valid_from'] ?? null, fn ($query, $from) => $query->where(fn ($period) => $period->whereNull('valid_until')->orWhereDate('valid_until', '>=', $from)))
                ->exists();
            if ($overlap) throw ValidationException::withMessages(['starts_at' => 'Este horario se superpone con otra disponibilidad del mismo día.']);
            return $referee->availabilities()->create($data);
        });
        $this->audit->log($request, 'referee.availability.created', $availability, $league, [], $availability->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Disponibilidad del árbitro agregada.');
    }

    public function destroyAvailability(Request $request, RefereeAvailability $availability): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $availability->load('referee');
        $league = $this->league($request);
        $this->assertReferee($availability->referee, $league);
        $this->assertCanEditOwnOrManage($request, $availability->referee, $league);
        $old = $availability->toArray();
        $this->audit->log($request, 'referee.availability.deleted', $availability, $league, $old, [], $data['reason']);
        $availability->delete();
        return back()->with('success', 'Disponibilidad eliminada.');
    }

    public function storeObservation(StoreRefereeObservationRequest $request, Referee $referee): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertCanManage($request, $league);
        $this->assertReferee($referee, $league);
        $observation = $referee->observations()->create([
            ...$request->safe()->only(['observed_on', 'observation']),
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'referee.observation.created', $observation, $league, [], $observation->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Observación interna registrada.');
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
    private function assertReferee(Referee $referee, League $league): void { abort_unless($referee->league_id === $league->id, 404); }

    private function canManage(Request $request, League $league): bool
    {
        $roleId = (int) $request->session()->get(LeagueContext::ROLE_KEY);
        return $request->user()->hasPermission('referees.manage') || $request->user()->hasLeaguePermission('referees.manage', $league->id, $roleId);
    }

    private function assertCanManage(Request $request, League $league): void { abort_unless($this->canManage($request, $league), 403); }

    private function assertCanEditOwnOrManage(Request $request, Referee $referee, League $league): void
    {
        abort_unless($this->canManage($request, $league) || $referee->user_id === $request->user()->id, 403);
    }

    private function assertEligibleUser(League $league, int $userId): void
    {
        $eligible = User::whereKey($userId)->whereHas('leagueMemberships', fn ($memberships) => $memberships
            ->where('league_id', $league->id)
            ->where('status', 'active')
            ->whereHas('role', fn ($role) => $role->where('slug', 'referee')))->exists();
        if (! $eligible) throw ValidationException::withMessages(['user_id' => 'El usuario debe tener un acceso activo como árbitro en esta liga.']);
        if ($league->referees()->where('user_id', $userId)->exists()) throw ValidationException::withMessages(['user_id' => 'Este usuario ya tiene un expediente arbitral en la liga.']);
    }
}
