<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\MatchReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchReport extends Model
{
    protected $fillable = ['match_id', 'folio', 'status', 'home_score', 'away_score', 'home_no_show', 'away_no_show', 'summary', 'incidents', 'created_by', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes', 'approved_at'];

    protected function casts(): array
    {
        return ['status' => MatchReportStatus::class, 'home_score' => 'integer', 'away_score' => 'integer', 'home_no_show' => 'boolean', 'away_no_show' => 'boolean', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function match(): BelongsTo { return $this->belongsTo(GameMatch::class, 'match_id'); }
    public function events(): HasMany { return $this->hasMany(MatchReportEvent::class)->orderBy('minute')->orderBy('id'); }
    public function confirmations(): HasMany { return $this->hasMany(MatchReportConfirmation::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
