<?php

namespace App\Domain\Player\Models;

use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Division;
use App\Domain\Competition\Models\Season;
use App\Domain\Identity\Models\User;
use App\Domain\Player\Enums\RegistrationStatus;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerRegistration extends Model
{
    protected $fillable = [
        'player_id', 'team_id', 'team_participation_id', 'competition_id', 'season_id',
        'division_id', 'jersey_number', 'status', 'requested_at', 'requested_by',
        'reviewed_at', 'reviewed_by', 'review_reason', 'released_at', 'released_by', 'release_reason',
    ];

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class, 'requested_at' => 'datetime', 'reviewed_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function teamParticipation(): BelongsTo { return $this->belongsTo(TeamParticipation::class); }
    public function competition(): BelongsTo { return $this->belongsTo(Competition::class); }
    public function season(): BelongsTo { return $this->belongsTo(Season::class); }
    public function division(): BelongsTo { return $this->belongsTo(Division::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
