<?php

namespace App\Domain\Referee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefereeAvailability extends Model
{
    protected $fillable = ['referee_id', 'weekday', 'starts_at', 'ends_at', 'valid_from', 'valid_until'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'valid_from' => 'date', 'valid_until' => 'date'];
    }

    public function referee(): BelongsTo { return $this->belongsTo(Referee::class); }
}
