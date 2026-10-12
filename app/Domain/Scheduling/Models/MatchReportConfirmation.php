<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchReportConfirmation extends Model
{
    protected $fillable = ['team_participation_id', 'representative_user_id', 'decision', 'notes', 'decided_at'];
    protected function casts(): array { return ['decided_at' => 'datetime']; }
    public function report(): BelongsTo { return $this->belongsTo(MatchReport::class, 'match_report_id'); }
    public function teamParticipation(): BelongsTo { return $this->belongsTo(TeamParticipation::class); }
    public function representative(): BelongsTo { return $this->belongsTo(User::class, 'representative_user_id'); }
}
