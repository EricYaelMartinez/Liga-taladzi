<?php

namespace App\Domain\Player\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['player_id', 'type', 'path', 'original_name', 'mime_type', 'size_bytes', 'retain_until', 'uploaded_by', 'deleted_by', 'deletion_reason'];
    protected $hidden = ['path'];
    protected function casts(): array { return ['retain_until' => 'date']; }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function deleter(): BelongsTo { return $this->belongsTo(User::class, 'deleted_by'); }
}
