<?php

namespace App\Domain\Player\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Team\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMovement extends Model
{
    protected $fillable = ['player_id', 'player_registration_id', 'from_team_id', 'to_team_id', 'type', 'reason', 'performed_by', 'occurred_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function registration(): BelongsTo { return $this->belongsTo(PlayerRegistration::class, 'player_registration_id'); }
    public function fromTeam(): BelongsTo { return $this->belongsTo(Team::class, 'from_team_id'); }
    public function toTeam(): BelongsTo { return $this->belongsTo(Team::class, 'to_team_id'); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
}
