<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchReportEvent extends Model
{
    protected $fillable = ['team_participation_id', 'player_registration_id', 'related_player_registration_id', 'type', 'minute', 'period', 'notes', 'created_by'];
    protected function casts(): array { return ['minute' => 'integer']; }
    public function report(): BelongsTo { return $this->belongsTo(MatchReport::class, 'match_report_id'); }
    public function teamParticipation(): BelongsTo { return $this->belongsTo(TeamParticipation::class); }
    public function playerRegistration(): BelongsTo { return $this->belongsTo(PlayerRegistration::class); }
    public function relatedPlayerRegistration(): BelongsTo { return $this->belongsTo(PlayerRegistration::class, 'related_player_registration_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
