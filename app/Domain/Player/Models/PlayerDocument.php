<?php

namespace App\Domain\Player\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerDocument extends Model
{
    protected $fillable = ['player_id', 'type', 'path', 'retain_until', 'uploaded_by'];
    protected $hidden = ['path'];
    protected function casts(): array { return ['retain_until' => 'date']; }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
