<?php

namespace App\Domain\League\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueSetting extends Model
{
    protected $fillable = [
        'league_id',
        'match_periods',
        'period_duration_minutes',
        'halftime_minutes',
        'schedule_buffer_minutes',
        'appeal_deadline_hours',
        'payment_grace_days',
        'reactivation_window_days',
        'bond_enabled',
        'bond_amount',
        'currency',
        'revision',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'bond_enabled' => 'boolean',
            'bond_amount' => 'decimal:2',
            'revision' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
