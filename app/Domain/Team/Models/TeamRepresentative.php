<?php

namespace App\Domain\Team\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\LeagueMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamRepresentative extends Model
{
    protected $fillable = ['team_id', 'league_membership_id', 'user_id', 'photo_path', 'ine_path', 'status', 'started_at', 'ended_at', 'assigned_by'];
    protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime']; }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function membership(): BelongsTo { return $this->belongsTo(LeagueMembership::class, 'league_membership_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
