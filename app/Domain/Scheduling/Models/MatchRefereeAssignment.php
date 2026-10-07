<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Referee\Models\Referee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRefereeAssignment extends Model
{
    protected $fillable = ['match_id', 'referee_id', 'role', 'assigned_by'];

    public function match(): BelongsTo { return $this->belongsTo(GameMatch::class, 'match_id'); }
    public function referee(): BelongsTo { return $this->belongsTo(Referee::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
