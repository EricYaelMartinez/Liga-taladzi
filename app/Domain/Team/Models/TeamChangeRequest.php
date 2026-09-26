<?php

namespace App\Domain\Team\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamChangeRequest extends Model
{
    protected $fillable = ['team_id', 'requested_by', 'changes', 'status', 'request_reason', 'reviewed_by', 'reviewed_at', 'review_reason'];
    protected function casts(): array { return ['changes' => 'array', 'reviewed_at' => 'datetime']; }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
