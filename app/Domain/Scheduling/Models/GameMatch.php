<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Competition\Models\Competition;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\MatchStatus;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    protected $table = 'matches';

    protected $dateFormat = 'Y-m-d H:i:s P';

    protected $fillable = [
        'competition_id', 'matchday_id', 'home_team_participation_id', 'away_team_participation_id',
        'playing_field_id', 'scheduled_at', 'scheduled_end_at', 'duration_minutes', 'status',
        'public_notes', 'leg_number', 'pairing_key', 'created_by',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'scheduled_end_at' => 'datetime', 'duration_minutes' => 'integer', 'leg_number' => 'integer', 'status' => MatchStatus::class];
    }

    public function competition(): BelongsTo { return $this->belongsTo(Competition::class); }
    public function matchday(): BelongsTo { return $this->belongsTo(Matchday::class); }
    public function homeParticipation(): BelongsTo { return $this->belongsTo(TeamParticipation::class, 'home_team_participation_id'); }
    public function awayParticipation(): BelongsTo { return $this->belongsTo(TeamParticipation::class, 'away_team_participation_id'); }
    public function field(): BelongsTo { return $this->belongsTo(PlayingField::class, 'playing_field_id'); }
    public function refereeAssignments(): HasMany { return $this->hasMany(MatchRefereeAssignment::class, 'match_id'); }
    public function lineups(): HasMany { return $this->hasMany(MatchLineup::class, 'match_id'); }
    public function scheduleChanges(): HasMany { return $this->hasMany(MatchScheduleChange::class, 'match_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
