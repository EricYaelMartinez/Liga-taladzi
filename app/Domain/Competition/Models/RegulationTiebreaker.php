<?php

namespace App\Domain\Competition\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulationTiebreaker extends Model
{
    public $timestamps = false;

    protected $fillable = ['regulation_id', 'criterion', 'priority'];

    public function regulation(): BelongsTo { return $this->belongsTo(Regulation::class); }
}
