<?php

namespace App\Domain\Team\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamNameHistory extends Model
{
    protected $fillable = ['team_id', 'name', 'short_name', 'valid_from', 'valid_until', 'changed_by', 'reason'];
    protected function casts(): array { return ['valid_from' => 'datetime', 'valid_until' => 'datetime']; }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
