<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchScheduleChange extends Model
{
    public $timestamps = false;
    protected $fillable = ['match_id', 'type', 'old_values', 'new_values', 'reason', 'performed_by', 'occurred_at'];
    protected function casts(): array { return ['old_values' => 'array', 'new_values' => 'array', 'occurred_at' => 'datetime']; }
    public function match(): BelongsTo { return $this->belongsTo(GameMatch::class, 'match_id'); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
}
