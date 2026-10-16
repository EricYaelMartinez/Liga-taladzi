<?php

namespace App\Domain\Finance\Models;

use App\Domain\Competition\Models\Season;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCharge extends Model
{
    protected $fillable = ['league_id', 'team_id', 'team_participation_id', 'season_id', 'concept_id', 'source_key', 'description', 'type', 'original_amount', 'balance', 'issued_on', 'due_on', 'grace_until', 'status', 'paid_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by'];

    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2', 'balance' => 'decimal:2', 'issued_on' => 'date', 'due_on' => 'date', 'grace_until' => 'date', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function participation(): BelongsTo { return $this->belongsTo(TeamParticipation::class, 'team_participation_id'); }
    public function season(): BelongsTo { return $this->belongsTo(Season::class); }
    public function concept(): BelongsTo { return $this->belongsTo(FinanceConcept::class, 'concept_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function allocations(): HasMany { return $this->hasMany(FinancePaymentAllocation::class, 'charge_id'); }
}
