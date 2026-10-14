<?php

namespace App\Domain\Discipline\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinePolicy extends Model
{
    protected $fillable = ['league_id', 'automatic_sanctions_enabled', 'yellow_card_threshold', 'yellow_suspension_matches', 'second_yellow_suspension_matches', 'red_card_suspension_matches', 'updated_by'];

    protected function casts(): array
    {
        return ['automatic_sanctions_enabled' => 'boolean', 'yellow_card_threshold' => 'integer', 'yellow_suspension_matches' => 'integer', 'second_yellow_suspension_matches' => 'integer', 'red_card_suspension_matches' => 'integer'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
