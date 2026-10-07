<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Competition\Models\Competition;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\MatchPhase;
use App\Domain\Scheduling\Enums\MatchdayStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matchday extends Model
{
    protected $fillable = ['competition_id', 'number', 'name', 'phase', 'starts_on', 'ends_on', 'status', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['phase' => MatchPhase::class, 'status' => MatchdayStatus::class, 'starts_on' => 'date', 'ends_on' => 'date', 'published_at' => 'datetime'];
    }

    public function competition(): BelongsTo { return $this->belongsTo(Competition::class); }
    public function matches(): HasMany { return $this->hasMany(GameMatch::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
