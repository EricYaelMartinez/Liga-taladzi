<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthenticationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'email', 'successful', 'failure_reason',
        'ip_address', 'user_agent', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'occurred_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
