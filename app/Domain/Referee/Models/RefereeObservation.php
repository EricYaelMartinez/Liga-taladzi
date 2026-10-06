<?php

namespace App\Domain\Referee\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefereeObservation extends Model
{
    protected $fillable = ['referee_id', 'observed_on', 'observation', 'created_by'];

    protected function casts(): array { return ['observed_on' => 'date']; }

    public function referee(): BelongsTo { return $this->belongsTo(Referee::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
