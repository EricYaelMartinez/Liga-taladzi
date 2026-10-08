<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Referee\Models\Referee;
use App\Domain\Referee\Services\RefereeAvailabilityService;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Team\Models\TeamParticipation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchSchedulingService
{
    public function __construct(
        private readonly FieldAvailabilityService $fields,
        private readonly RefereeAvailabilityService $referees,
    ) {}

    /** @deprecated Se conserva para compatibilidad con el formulario original del Módulo 10. */
    public function schedule(GameMatch $match, array $data, User $actor): GameMatch
    {
        return DB::transaction(function () use ($match, $data, $actor): GameMatch {
            $match->loadMissing('refereeAssignments');
            if ($match->status->value === 'suspended' && array_key_exists('referees', $data)) {
                $current = $match->refereeAssignments->pluck('referee_id')->sort()->values()->all();
                $requested = collect($data['referees'])->filter()->sort()->values()->all();
                if ($current !== $requested) {
                    throw ValidationException::withMessages(['central_referee_id' => 'No se permite reemplazar árbitros después de iniciado el partido.']);
                }
            }
            $updated = $this->updateSchedule($match, $data, $actor);
            if (array_key_exists('referees', $data)) {
                $updated = $this->assignReferees($updated, $data['referees'], $data['reason'], $actor);
            }
            return $updated;
        });
    }

    public function updateSchedule(GameMatch $match, array $data, User $actor): GameMatch
    {
        $match->loadMissing('competition.tournament.season', 'competition.regulation', 'matchday', 'refereeAssignments.referee.user');
        $this->assertScheduleMayChange($match);

        $startsAt = Carbon::parse($data['scheduled_at'], config('app.timezone'));
        $endsAt = $startsAt->copy()->addMinutes($match->duration_minutes);
        $buffer = max(0, (int) ($match->competition->regulation->league_settings_snapshot['schedule_buffer_minutes'] ?? 0));
        $reservedUntil = $endsAt->copy()->addMinutes($buffer);
        $leagueId = $match->competition->tournament->season->league_id;
        $field = PlayingField::whereHas('venue', fn ($query) => $query->where('league_id', $leagueId))->findOrFail($data['playing_field_id']);

        return DB::transaction(function () use ($match, $data, $actor, $field, $startsAt, $endsAt, $reservedUntil, $buffer): GameMatch {
            PlayingField::whereKey($field->id)->lockForUpdate()->firstOrFail();
            TeamParticipation::whereIn('id', [$match->home_team_participation_id, $match->away_team_participation_id])->orderBy('id')->lockForUpdate()->get();
            $assignedRefereeIds = $match->refereeAssignments->pluck('referee_id')->all();
            if ($assignedRefereeIds) Referee::whereIn('id', $assignedRefereeIds)->orderBy('id')->lockForUpdate()->get();
            $lockedMatch = GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $lockedMatch->loadMissing('matchday', 'refereeAssignments.referee.user');
            $this->assertScheduleMayChange($lockedMatch);

            if (! $this->fields->isAvailable($field, $startsAt, $endsAt, $buffer)) {
                throw ValidationException::withMessages(['playing_field_id' => 'La cancha no está disponible durante todo el partido y su margen configurado.']);
            }
            $this->assertDailyCapacity($lockedMatch, $field, $startsAt);
            $this->assertNoFieldConflict($lockedMatch, $field->id, $startsAt, $reservedUntil, $buffer);
            $this->assertNoTeamConflict($lockedMatch, $startsAt, $reservedUntil, $buffer);
            foreach ($lockedMatch->refereeAssignments as $assignment) {
                if (! $this->referees->isAvailable($assignment->referee, $startsAt, $reservedUntil)) {
                    throw ValidationException::withMessages(['scheduled_at' => "{$assignment->referee->user->name} no está disponible en el nuevo horario."]);
                }
            }
            if ($assignedRefereeIds) $this->assertNoRefereeConflict($lockedMatch, $assignedRefereeIds, $startsAt, $reservedUntil, $buffer);

            $old = $this->snapshot($lockedMatch);
            $lockedMatch->update([
                'playing_field_id' => $field->id,
                'scheduled_at' => $startsAt,
                'scheduled_end_at' => $endsAt,
                'public_notes' => $data['public_notes'] ?? $lockedMatch->public_notes,
                'status' => $lockedMatch->status->value === 'suspended'
                    ? 'suspended'
                    : ($lockedMatch->matchday->status->value === 'published' ? 'scheduled' : 'draft'),
            ]);
            $new = $this->snapshot($lockedMatch->fresh()->load('refereeAssignments'));
            $lockedMatch->scheduleChanges()->create([
                'type' => $old['scheduled_at'] ? 'rescheduled' : 'scheduled',
                'old_values' => $old, 'new_values' => $new, 'reason' => $data['reason'],
                'performed_by' => $actor->id, 'occurred_at' => now(),
            ]);
            return $lockedMatch->fresh();
        });
    }

    public function assignReferees(GameMatch $match, array $refereeRoles, string $reason, User $actor): GameMatch
    {
        $match->loadMissing('competition.tournament.season', 'competition.regulation', 'matchday', 'refereeAssignments');
        if (! $match->scheduled_at || ! $match->scheduled_end_at) {
            throw ValidationException::withMessages(['central_referee_id' => 'Primero debes asignar fecha, horario y cancha al partido.']);
        }
        if (! in_array($match->status->value, ['draft', 'scheduled', 'postponed', 'suspended'], true)) {
            throw ValidationException::withMessages(['central_referee_id' => 'Este partido ya no admite cambios de árbitros.']);
        }

        $refereeIds = collect($refereeRoles)->filter()->values();
        if ($refereeIds->isEmpty()) throw ValidationException::withMessages(['central_referee_id' => 'Debes asignar un árbitro central.']);
        if ($refereeIds->count() !== $refereeIds->unique()->count()) {
            throw ValidationException::withMessages(['central_referee_id' => 'Un árbitro no puede ocupar más de una función en el mismo partido.']);
        }
        if ($match->matchday->phase->value !== 'knockout' && $refereeIds->count() > 1) {
            throw ValidationException::withMessages(['assistant_1_referee_id' => 'Los asistentes y cuarto árbitro solo pueden asignarse en fase eliminatoria.']);
        }

        $leagueId = $match->competition->tournament->season->league_id;
        $selected = Referee::where('league_id', $leagueId)->whereIn('id', $refereeIds)->with('user:id,name')->get()->keyBy('id');
        if ($selected->count() !== $refereeIds->count()) {
            throw ValidationException::withMessages(['central_referee_id' => 'Uno de los árbitros no pertenece a la liga activa.']);
        }
        $requested = $refereeIds->sort()->values()->all();
        if (in_array($match->status->value, ['in_progress', 'suspended'], true)
            && $match->refereeAssignments->pluck('referee_id')->sort()->values()->all() !== $requested) {
            throw ValidationException::withMessages(['central_referee_id' => 'No se permite reemplazar árbitros después de iniciado el partido.']);
        }

        $buffer = max(0, (int) ($match->competition->regulation->league_settings_snapshot['schedule_buffer_minutes'] ?? 0));
        $reservedUntil = $match->scheduled_end_at->copy()->addMinutes($buffer);

        return DB::transaction(function () use ($match, $refereeRoles, $reason, $actor, $selected, $reservedUntil, $buffer): GameMatch {
            Referee::whereIn('id', $selected->keys())->orderBy('id')->lockForUpdate()->get();
            $lockedMatch = GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $lockedMatch->load('refereeAssignments');
            foreach ($selected as $referee) {
                if (! $this->referees->isAvailable($referee, $lockedMatch->scheduled_at, $reservedUntil)) {
                    throw ValidationException::withMessages(['central_referee_id' => "{$referee->user->name} no está disponible en este horario."]);
                }
            }
            $this->assertNoRefereeConflict($lockedMatch, $selected->keys()->all(), $lockedMatch->scheduled_at, $reservedUntil, $buffer);
            $old = $this->snapshot($lockedMatch);
            $lockedMatch->refereeAssignments()->delete();
            foreach ($refereeRoles as $role => $refereeId) {
                if ($refereeId) $lockedMatch->refereeAssignments()->create(['referee_id' => $refereeId, 'role' => $role, 'assigned_by' => $actor->id]);
            }
            $new = $this->snapshot($lockedMatch->fresh()->load('refereeAssignments'));
            $lockedMatch->scheduleChanges()->create([
                'type' => 'referees_assigned', 'old_values' => $old, 'new_values' => $new,
                'reason' => $reason, 'performed_by' => $actor->id, 'occurred_at' => now(),
            ]);
            return $lockedMatch->fresh();
        });
    }

    private function assertScheduleMayChange(GameMatch $match): void
    {
        if (! in_array($match->status->value, ['draft', 'scheduled', 'postponed', 'suspended'], true)) {
            throw ValidationException::withMessages(['scheduled_at' => 'Este partido ya no admite cambios de horario o cancha.']);
        }
    }

    private function assertDailyCapacity(GameMatch $match, PlayingField $field, Carbon $startsAt): void
    {
        $field->loadMissing('venue.league.setting');
        $maximum = (int) ($field->max_matches_per_day ?? $field->venue->league->setting?->default_max_matches_per_field_day ?? 6);
        $used = GameMatch::query()->whereKeyNot($match->id)->where('playing_field_id', $field->id)
            ->whereIn('status', ['draft', 'scheduled', 'in_progress', 'suspended'])
            ->whereDate('scheduled_at', $startsAt->toDateString())->count();
        if ($used >= $maximum) {
            throw ValidationException::withMessages(['playing_field_id' => "La cancha alcanzó su máximo de {$maximum} partidos para ese día."]);
        }
    }

    private function conflicts(GameMatch $match, Carbon $startsAt, Carbon $reservedUntil, int $buffer, callable $scope): bool
    {
        return $scope(GameMatch::query()->whereKeyNot($match->id)
            ->whereIn('status', ['draft', 'scheduled', 'in_progress', 'suspended'])
            ->whereNotNull('scheduled_at')->whereNotNull('scheduled_end_at'))
            ->get(['id', 'scheduled_at', 'scheduled_end_at'])
            ->contains(fn (GameMatch $existing) => $existing->scheduled_at->lt($reservedUntil)
                && $existing->scheduled_end_at->copy()->addMinutes($buffer)->gt($startsAt));
    }

    private function assertNoFieldConflict(GameMatch $match, int $fieldId, Carbon $startsAt, Carbon $reservedUntil, int $buffer): void
    {
        if ($this->conflicts($match, $startsAt, $reservedUntil, $buffer, fn ($query) => $query->where('playing_field_id', $fieldId))) {
            throw ValidationException::withMessages(['playing_field_id' => 'La cancha ya tiene otro partido en un horario incompatible.']);
        }
    }

    private function assertNoTeamConflict(GameMatch $match, Carbon $startsAt, Carbon $reservedUntil, int $buffer): void
    {
        $teams = [$match->home_team_participation_id, $match->away_team_participation_id];
        if ($this->conflicts($match, $startsAt, $reservedUntil, $buffer, fn ($query) => $query->where(fn ($teamsQuery) => $teamsQuery
            ->whereIn('home_team_participation_id', $teams)->orWhereIn('away_team_participation_id', $teams)))) {
            throw ValidationException::withMessages(['scheduled_at' => 'Uno de los equipos ya tiene otro partido en un horario incompatible.']);
        }
    }

    private function assertNoRefereeConflict(GameMatch $match, array $refereeIds, Carbon $startsAt, Carbon $reservedUntil, int $buffer): void
    {
        if ($this->conflicts($match, $startsAt, $reservedUntil, $buffer, fn ($query) => $query->whereHas('refereeAssignments', fn ($assignments) => $assignments->whereIn('referee_id', $refereeIds)))) {
            throw ValidationException::withMessages(['central_referee_id' => 'Uno de los árbitros ya está asignado a otro partido en un horario incompatible.']);
        }
    }

    private function snapshot(GameMatch $match): array
    {
        return [
            'playing_field_id' => $match->playing_field_id,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'scheduled_end_at' => $match->scheduled_end_at?->toIso8601String(),
            'status' => $match->status->value,
            'referees' => $match->refereeAssignments->pluck('referee_id', 'role')->all(),
        ];
    }

}
