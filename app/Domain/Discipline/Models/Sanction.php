<?php

namespace App\Domain\Discipline\Models;

use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Season;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Player\Models\Player;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\MatchReportEvent;
use App\Domain\Team\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sanction extends Model
{
    protected $fillable = ['league_id', 'season_id', 'competition_id', 'team_id', 'player_id', 'subject_type', 'subject_name', 'kind', 'source', 'source_key', 'match_report_id', 'match_id', 'source_event_id', 'reason', 'legal_basis', 'matches_total', 'matches_served', 'points_deducted', 'fine_amount', 'status', 'issued_at', 'appeal_deadline_at', 'completed_at', 'revoked_at', 'created_by', 'resolved_by', 'resolution_notes'];

    protected function casts(): array
    {
        return ['matches_total' => 'integer', 'matches_served' => 'integer', 'points_deducted' => 'integer', 'fine_amount' => 'decimal:2', 'issued_at' => 'datetime', 'appeal_deadline_at' => 'datetime', 'completed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function season(): BelongsTo { return $this->belongsTo(Season::class); }
    public function competition(): BelongsTo { return $this->belongsTo(Competition::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function report(): BelongsTo { return $this->belongsTo(MatchReport::class, 'match_report_id'); }
    public function match(): BelongsTo { return $this->belongsTo(GameMatch::class, 'match_id'); }
    public function sourceEvent(): BelongsTo { return $this->belongsTo(MatchReportEvent::class, 'source_event_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function servedMatches(): HasMany { return $this->hasMany(SanctionServedMatch::class); }
    public function appeal(): HasOne { return $this->hasOne(SanctionAppeal::class); }
}
