<?php

namespace App\Domain\Finance\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceConcept extends Model
{
    protected $fillable = ['league_id', 'name', 'description', 'type', 'default_amount', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2', 'active' => 'boolean'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function charges(): HasMany { return $this->hasMany(FinanceCharge::class, 'concept_id'); }
}
