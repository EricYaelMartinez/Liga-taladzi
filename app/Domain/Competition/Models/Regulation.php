<?php

namespace App\Domain\Competition\Models;

use App\Domain\Competition\Enums\RegulationStatus;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regulation extends Model
{
    protected $fillable = [
        'league_id', 'name', 'version', 'status', 'points_win', 'points_draw', 'points_loss',
        'walkover_home_goals', 'walkover_away_goals', 'fair_play_yellow_points',
        'fair_play_second_yellow_points', 'fair_play_red_points', 'league_settings_snapshot',
        'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegulationStatus::class,
            'league_settings_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function tiebreakers(): HasMany { return $this->hasMany(RegulationTiebreaker::class)->orderBy('priority'); }
    public function competitions(): HasMany { return $this->hasMany(Competition::class); }
    public function isMutable(): bool { return $this->status === RegulationStatus::Draft; }
}
