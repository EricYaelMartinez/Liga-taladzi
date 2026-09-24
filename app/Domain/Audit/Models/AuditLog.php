<?php

namespace App\Domain\Audit\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'league_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'reason', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
