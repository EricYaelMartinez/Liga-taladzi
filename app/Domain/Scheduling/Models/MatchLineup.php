<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\LineupStatus;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchLineup extends Model
{
    protected $fillable = ['match_id', 'team_participation_id', 'status', 'created_by', 'submitted_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['status' => LineupStatus::class, 'submitted_at' => 'datetime'];
    }

    public function match(): BelongsTo { return $this->belongsTo(GameMatch::class, 'match_id'); }
    public function teamParticipation(): BelongsTo { return $this->belongsTo(TeamParticipation::class); }
    public function players(): HasMany { return $this->hasMany(MatchLineupPlayer::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
}
