<?php

namespace App\Domain\Player\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Player\Enums\CredentialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerCredential extends Model
{
    protected $fillable = [
        'league_id', 'player_registration_id', 'sequence', 'folio', 'status', 'snapshot',
        'issued_at', 'issued_by', 'revoked_at', 'revoked_by', 'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CredentialStatus::class,
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function registration(): BelongsTo { return $this->belongsTo(PlayerRegistration::class, 'player_registration_id'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function revoker(): BelongsTo { return $this->belongsTo(User::class, 'revoked_by'); }
}
