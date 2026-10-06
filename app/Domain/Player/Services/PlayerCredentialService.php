<?php

namespace App\Domain\Player\Services;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Player\Enums\CredentialStatus;
use App\Domain\Player\Models\PlayerCredential;
use App\Domain\Player\Models\PlayerRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerCredentialService
{
    public function issue(PlayerRegistration $registration, User $user): PlayerCredential
    {
        return DB::transaction(function () use ($registration, $user): PlayerCredential {
            $registration->refresh()->loadMissing(['player', 'team', 'season', 'competition.category', 'competition.division']);
            if ($registration->status->value !== 'active' || $registration->player->status->value !== 'active') {
                throw ValidationException::withMessages(['credential' => 'Solo se emiten credenciales para jugadores activos y aprobados.']);
            }

            $existing = $registration->credentials()->where('status', CredentialStatus::Active->value)->lockForUpdate()->first();
            if ($existing) return $existing;

            League::whereKey($registration->player->league_id)->lockForUpdate()->firstOrFail();
            $sequence = ((int) PlayerCredential::where('league_id', $registration->player->league_id)->max('sequence')) + 1;
            $year = $registration->season->starts_on?->format('Y') ?? now()->format('Y');

            return $registration->credentials()->create([
                'league_id' => $registration->player->league_id,
                'sequence' => $sequence,
                'folio' => sprintf('L%04d-%s-%06d', $registration->player->league_id, $year, $sequence),
                'status' => CredentialStatus::Active,
                'snapshot' => [
                    'player' => $registration->player->full_name,
                    'team' => $registration->team->name,
                    'season' => $registration->season->name,
                    'competition' => $registration->competition->name,
                    'category' => $registration->competition->category->name,
                    'division' => $registration->competition->division->name,
                    'position' => $registration->player->position,
                    'jersey_number' => $registration->jersey_number,
                ],
                'issued_at' => now(),
                'issued_by' => $user->id,
            ]);
        });
    }

    public function revoke(PlayerRegistration $registration, User $user, string $reason): ?PlayerCredential
    {
        return DB::transaction(function () use ($registration, $user, $reason): ?PlayerCredential {
            $credential = $registration->credentials()->where('status', CredentialStatus::Active->value)->lockForUpdate()->first();
            if (! $credential) return null;

            $credential->update([
                'status' => CredentialStatus::Revoked,
                'revoked_at' => now(),
                'revoked_by' => $user->id,
                'revocation_reason' => $reason,
            ]);
            return $credential;
        });
    }
}
