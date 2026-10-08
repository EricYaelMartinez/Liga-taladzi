<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Scheduling\Enums\LineupStatus;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchLineup;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchLineupService
{
    private const MIN_STARTERS = 8;

    private const MAX_STARTERS = 11;

    public function save(GameMatch $match, TeamParticipation $participation, array $players, User $actor): MatchLineup
    {
        $this->assertParticipation($match, $participation);
        $this->assertMatchAcceptsLineups($match);
        $selection = collect($players);
        if ($selection->where('is_captain', true)->count() > 1) {
            throw ValidationException::withMessages(['players' => 'Solo puede seleccionarse un capitán por equipo.']);
        }
        if ($selection->where('role', 'starter')->count() > self::MAX_STARTERS) {
            throw ValidationException::withMessages(['players' => 'La alineación no puede tener más de 11 jugadores titulares.']);
        }

        return DB::transaction(function () use ($match, $participation, $players, $actor): MatchLineup {
            GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $lineup = MatchLineup::where('match_id', $match->id)
                ->where('team_participation_id', $participation->id)
                ->lockForUpdate()->first();
            if ($lineup?->status === LineupStatus::Submitted) {
                throw ValidationException::withMessages(['lineup' => 'La alineación ya fue enviada y no admite modificaciones.']);
            }

            $registrationIds = collect($players)->pluck('player_registration_id')->map(fn ($id) => (int) $id)->all();
            $registrations = PlayerRegistration::query()
                ->whereIn('id', $registrationIds)
                ->where('team_participation_id', $participation->id)
                ->where('status', 'active')
                ->whereHas('player', fn ($query) => $query->where('status', 'active'))
                ->with('player:id,full_name,position,status')
                ->lockForUpdate()->get()->keyBy('id');
            if ($registrations->count() !== count($registrationIds)) {
                throw ValidationException::withMessages(['players' => 'Uno de los jugadores no está activo y aprobado en la plantilla de este equipo.']);
            }

            $lineup ??= MatchLineup::create([
                'match_id' => $match->id,
                'team_participation_id' => $participation->id,
                'status' => LineupStatus::Draft,
                'created_by' => $actor->id,
            ]);
            $lineup->players()->delete();
            foreach (array_values($players) as $order => $item) {
                $registration = $registrations->get((int) $item['player_registration_id']);
                $lineup->players()->create([
                    'player_registration_id' => $registration->id,
                    'role' => $item['role'],
                    'jersey_number' => $registration->jersey_number,
                    'position' => $registration->player->position,
                    'is_captain' => (bool) $item['is_captain'],
                    'display_order' => $order,
                ]);
            }

            return $lineup->fresh()->load('players.registration.player');
        });
    }

    public function submit(GameMatch $match, TeamParticipation $participation, User $actor): MatchLineup
    {
        $this->assertParticipation($match, $participation);
        $this->assertMatchAcceptsLineups($match);

        return DB::transaction(function () use ($match, $participation, $actor): MatchLineup {
            GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $lineup = MatchLineup::where('match_id', $match->id)
                ->where('team_participation_id', $participation->id)
                ->lockForUpdate()->first();
            if (! $lineup) {
                throw ValidationException::withMessages(['lineup' => 'Guarda primero el borrador de la alineación.']);
            }
            if ($lineup->status === LineupStatus::Submitted) {
                throw ValidationException::withMessages(['lineup' => 'La alineación ya fue enviada.']);
            }
            $startersCount = $lineup->players()->where('role', 'starter')->count();
            if ($startersCount < self::MIN_STARTERS || $startersCount > self::MAX_STARTERS) {
                throw ValidationException::withMessages(['lineup' => 'Para enviar la alineación debes registrar entre 8 y 11 jugadores titulares.']);
            }
            $lineup->update([
                'status' => LineupStatus::Submitted,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);
            return $lineup->fresh()->load('players.registration.player', 'submittedBy:id,name');
        });
    }

    private function assertParticipation(GameMatch $match, TeamParticipation $participation): void
    {
        if (! in_array($participation->id, [$match->home_team_participation_id, $match->away_team_participation_id], true)) {
            throw ValidationException::withMessages(['team_participation_id' => 'El equipo no participa en este partido.']);
        }
    }

    private function assertMatchAcceptsLineups(GameMatch $match): void
    {
        if (! in_array($match->status->value, ['scheduled', 'postponed'], true)) {
            throw ValidationException::withMessages(['lineup' => 'El partido ya no admite captura o envío de alineaciones.']);
        }
    }
}
