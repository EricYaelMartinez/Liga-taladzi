<?php

namespace App\Domain\Notification\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'league_id', 'user_id', 'general_enabled', 'schedule_enabled',
        'finance_enabled', 'discipline_enabled',
    ];

    protected function casts(): array
    {
        return [
            'general_enabled' => 'boolean',
            'schedule_enabled' => 'boolean',
            'finance_enabled' => 'boolean',
            'discipline_enabled' => 'boolean',
        ];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
