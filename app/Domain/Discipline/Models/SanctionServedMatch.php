<?php

namespace App\Domain\Discipline\Models;

use App\Domain\Scheduling\Models\GameMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionServedMatch extends Model
{
    protected $fillable = ['sanction_id', 'match_id', 'served_at'];
    protected function casts(): array { return ['served_at' => 'datetime']; }
    public function sanction(): BelongsTo { return $this->belongsTo(Sanction::class); }
    public function match(): BelongsTo { return $this->belongsTo(GameMatch::class, 'match_id'); }
}
