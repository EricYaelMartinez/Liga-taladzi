<?php

namespace App\Domain\Competition\Models;

use App\Domain\Competition\Enums\SeasonStatus;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Season extends Model
{
    use SoftDeletes;

    protected $fillable = ['league_id', 'name', 'starts_on', 'ends_on', 'registration_starts_at', 'registration_ends_at', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'registration_starts_at' => 'datetime',
            'registration_ends_at' => 'datetime',
            'status' => SeasonStatus::class,
        ];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function tournaments(): HasMany { return $this->hasMany(Tournament::class); }
}
