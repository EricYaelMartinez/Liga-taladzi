<?php

namespace App\Domain\Finance\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Team\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancePayment extends Model
{
    protected $fillable = ['league_id', 'team_id', 'receipt_number', 'amount', 'method', 'paid_at', 'notes', 'received_by', 'voided_at', 'voided_by', 'void_reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
    public function allocations(): HasMany { return $this->hasMany(FinancePaymentAllocation::class, 'payment_id'); }
}
