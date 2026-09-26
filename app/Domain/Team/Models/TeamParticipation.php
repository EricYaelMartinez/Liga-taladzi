<?php

namespace App\Domain\Team\Models;

use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Division;
use App\Domain\Competition\Models\Season;
use App\Domain\Identity\Models\User;
use App\Domain\Team\Enums\ParticipationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamParticipation extends Model
{
    protected $fillable = [
        'team_id', 'competition_id', 'season_id', 'division_id', 'registered_name', 'status',
        'requested_at', 'requested_by', 'reviewed_at', 'reviewed_by', 'review_reason',
        'suspended_at', 'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'requested_at' => 'datetime', 'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime', 'reactivated_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function competition(): BelongsTo { return $this->belongsTo(Competition::class); }
    public function season(): BelongsTo { return $this->belongsTo(Season::class); }
    public function division(): BelongsTo { return $this->belongsTo(Division::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
