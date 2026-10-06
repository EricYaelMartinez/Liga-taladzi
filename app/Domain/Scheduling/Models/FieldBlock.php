<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldBlock extends Model
{
    protected $fillable = ['playing_field_id', 'type', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }

    public function field(): BelongsTo { return $this->belongsTo(PlayingField::class, 'playing_field_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
