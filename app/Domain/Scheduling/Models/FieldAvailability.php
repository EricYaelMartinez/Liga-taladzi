<?php

namespace App\Domain\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldAvailability extends Model
{
    protected $fillable = ['playing_field_id', 'weekday', 'starts_at', 'ends_at', 'valid_from', 'valid_until'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'valid_from' => 'date', 'valid_until' => 'date'];
    }

    public function field(): BelongsTo { return $this->belongsTo(PlayingField::class, 'playing_field_id'); }
}
