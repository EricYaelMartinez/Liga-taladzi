<?php

namespace App\Domain\Referee\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Referee\Enums\RefereeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'league_id', 'user_id', 'photo_path', 'contact_email', 'contact_phone',
        'category_level', 'status', 'joined_on', 'notes',
    ];

    protected function casts(): array
    {
        return ['status' => RefereeStatus::class, 'joined_on' => 'date'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function availabilities(): HasMany { return $this->hasMany(RefereeAvailability::class); }
    public function observations(): HasMany { return $this->hasMany(RefereeObservation::class); }
    public function matchAssignments(): HasMany { return $this->hasMany(\App\Domain\Scheduling\Models\MatchRefereeAssignment::class); }
}
