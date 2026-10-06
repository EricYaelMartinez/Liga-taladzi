<?php

namespace App\Http\Controllers\League;

use App\Domain\League\Models\League;
use App\Domain\Scheduling\Models\FieldAvailability;
use App\Domain\Scheduling\Models\FieldBlock;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Scheduling\Models\Venue;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFieldAvailabilityRequest;
use App\Http\Requests\StoreFieldBlockRequest;
use App\Http\Requests\StorePlayingFieldRequest;
use App\Http\Requests\StoreVenueRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FieldController extends Controller
{
    public function __construct(private readonly LeagueContext $context, private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $league = $this->league($request);
        return Inertia::render('League/Fields/Index', [
            'venues' => Venue::where('league_id', $league->id)->with([
                'fields' => fn ($query) => $query->with([
                    'divisions:id,name',
                    'availabilities' => fn ($availability) => $availability->orderBy('weekday')->orderBy('starts_at'),
                    'blocks' => fn ($block) => $block->where('ends_at', '>=', now()->startOfDay())->orderBy('starts_at'),
                ])->orderBy('name'),
            ])->orderBy('name')->get(),
            'divisions' => $league->divisions()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'scheduleBufferMinutes' => $league->setting?->schedule_buffer_minutes,
        ]);
    }

    public function storeVenue(StoreVenueRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertUniqueVenue($league, $request->string('name')->toString());
        $venue = $league->venues()->create($request->safe()->except('reason'));
        $this->audit->log($request, 'venue.created', $venue, $league, [], $venue->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Instalación registrada. Ahora puedes agregar sus canchas.');
    }

    public function updateVenue(StoreVenueRequest $request, Venue $venue): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertVenue($venue, $league);
        $this->assertUniqueVenue($league, $request->string('name')->toString(), $venue->id);
        $old = $venue->toArray();
        $venue->update($request->safe()->except('reason'));
        $this->audit->log($request, 'venue.updated', $venue, $league, $old, $venue->fresh()->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Instalación actualizada.');
    }

    public function storeField(StorePlayingFieldRequest $request, Venue $venue): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertVenue($venue, $league);
        $this->assertUniqueField($venue, $request->string('name')->toString());
        $divisions = $this->divisionIds($league, $request->validated('division_ids', []));
        $field = DB::transaction(function () use ($request, $venue, $divisions): PlayingField {
            $field = $venue->fields()->create($request->safe()->except(['division_ids', 'reason']));
            $field->divisions()->sync($divisions);
            return $field;
        });
        $this->audit->log($request, 'field.created', $field, $league, [], [...$field->toArray(), 'division_ids' => $divisions], $request->string('reason')->toString());
        return back()->with('success', 'Cancha registrada.');
    }

    public function updateField(StorePlayingFieldRequest $request, PlayingField $field): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertField($field, $league);
        $this->assertUniqueField($field->venue, $request->string('name')->toString(), $field->id);
        $divisions = $this->divisionIds($league, $request->validated('division_ids', []));
        $old = [...$field->toArray(), 'division_ids' => $field->divisions()->pluck('divisions.id')->all()];
        DB::transaction(function () use ($request, $field, $divisions): void {
            $field->update($request->safe()->except(['division_ids', 'reason']));
            $field->divisions()->sync($divisions);
        });
        $this->audit->log($request, 'field.updated', $field, $league, $old, [...$field->fresh()->toArray(), 'division_ids' => $divisions], $request->string('reason')->toString());
        return back()->with('success', 'Cancha actualizada.');
    }

    public function storeAvailability(StoreFieldAvailabilityRequest $request, PlayingField $field): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertField($field, $league);
        $data = $request->safe()->except('reason');
        $availability = DB::transaction(function () use ($field, $data): FieldAvailability {
            PlayingField::whereKey($field->id)->lockForUpdate()->firstOrFail();
            $overlap = $field->availabilities()->where('weekday', $data['weekday'])
                ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])
                ->when($data['valid_until'] ?? null, fn ($query, $until) => $query->where(fn ($period) => $period->whereNull('valid_from')->orWhereDate('valid_from', '<=', $until)))
                ->when($data['valid_from'] ?? null, fn ($query, $from) => $query->where(fn ($period) => $period->whereNull('valid_until')->orWhereDate('valid_until', '>=', $from)))
                ->exists();
            if ($overlap) throw ValidationException::withMessages(['starts_at' => 'Este horario se superpone con otra disponibilidad del mismo día.']);
            return $field->availabilities()->create($data);
        });
        $this->audit->log($request, 'field.availability.created', $availability, $league, [], $availability->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Disponibilidad semanal agregada.');
    }

    public function destroyAvailability(Request $request, FieldAvailability $availability): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $availability->load('field.venue');
        $league = $this->league($request);
        $this->assertField($availability->field, $league);
        $old = $availability->toArray();
        $this->audit->log($request, 'field.availability.deleted', $availability, $league, $old, [], $data['reason']);
        $availability->delete();
        return back()->with('success', 'Disponibilidad eliminada.');
    }

    public function storeBlock(StoreFieldBlockRequest $request, PlayingField $field): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertField($field, $league);
        $data = $request->validated();
        $block = DB::transaction(function () use ($field, $data, $request): FieldBlock {
            PlayingField::whereKey($field->id)->lockForUpdate()->firstOrFail();
            if ($field->blocks()->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'Este periodo se superpone con otro bloqueo de la cancha.']);
            }
            return $field->blocks()->create([...$data, 'created_by' => $request->user()->id]);
        });
        $this->audit->log($request, 'field.block.created', $block, $league, [], $block->toArray(), $data['reason']);
        return back()->with('success', 'Bloqueo registrado.');
    }

    public function destroyBlock(Request $request, FieldBlock $block): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $block->load('field.venue');
        $league = $this->league($request);
        $this->assertField($block->field, $league);
        $old = $block->toArray();
        $this->audit->log($request, 'field.block.deleted', $block, $league, $old, [], $data['reason']);
        $block->delete();
        return back()->with('success', 'Bloqueo eliminado.');
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
    private function assertVenue(Venue $venue, League $league): void { abort_unless($venue->league_id === $league->id, 404); }
    private function assertField(PlayingField $field, League $league): void { $field->loadMissing('venue'); $this->assertVenue($field->venue, $league); }

    private function assertUniqueVenue(League $league, string $name, ?int $except = null): void
    {
        if ($league->venues()->when($except, fn ($query) => $query->whereKeyNot($except))->whereRaw('LOWER(name) = ?', [Str::lower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una instalación con este nombre.']);
        }
    }

    private function assertUniqueField(Venue $venue, string $name, ?int $except = null): void
    {
        if ($venue->fields()->when($except, fn ($query) => $query->whereKeyNot($except))->whereRaw('LOWER(name) = ?', [Str::lower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una cancha con este nombre en la instalación.']);
        }
    }

    private function divisionIds(League $league, array $ids): array
    {
        $valid = $league->divisions()->whereKey($ids)->pluck('id')->all();
        if (count($valid) !== count($ids)) throw ValidationException::withMessages(['division_ids' => 'Una división no pertenece a la liga activa.']);
        return $valid;
    }
}
