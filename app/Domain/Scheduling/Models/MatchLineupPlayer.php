<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Scheduling\Enums\LineupPlayerRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchLineupPlayer extends Model
{
    protected $fillable = ['player_registration_id', 'role', 'jersey_number', 'position', 'is_captain', 'display_order'];

    protected function casts(): array
    {
        return ['role' => LineupPlayerRole::class, 'jersey_number' => 'integer', 'is_captain' => 'boolean', 'display_order' => 'integer'];
    }

    public function lineup(): BelongsTo { return $this->belongsTo(MatchLineup::class, 'match_lineup_id'); }
    public function registration(): BelongsTo { return $this->belongsTo(PlayerRegistration::class, 'player_registration_id'); }
}
