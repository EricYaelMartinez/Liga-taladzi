<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'charge_id', 'amount'];
    protected function casts(): array { return ['amount' => 'decimal:2']; }
    public function payment(): BelongsTo { return $this->belongsTo(FinancePayment::class, 'payment_id'); }
    public function charge(): BelongsTo { return $this->belongsTo(FinanceCharge::class, 'charge_id'); }
}
