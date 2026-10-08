<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTimeSlot extends Model
{
    protected $fillable = ['league_id', 'playing_field_id', 'weekday', 'starts_at', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'is_active' => 'boolean'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function field(): BelongsTo { return $this->belongsTo(PlayingField::class, 'playing_field_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
