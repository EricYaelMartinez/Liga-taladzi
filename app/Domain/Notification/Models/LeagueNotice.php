<?php

namespace App\Domain\Notification\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Notification\Enums\NoticeCategory;
use App\Domain\Notification\Enums\NoticePriority;
use App\Domain\Notification\Enums\NoticeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueNotice extends Model
{
    protected $fillable = [
        'league_id', 'title', 'body', 'category', 'priority', 'status', 'audience_type',
        'audience_role_slugs', 'audience_team_ids', 'created_by', 'published_by',
        'published_at', 'expires_at', 'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => NoticeCategory::class,
            'priority' => NoticePriority::class,
            'status' => NoticeStatus::class,
            'audience_role_slugs' => 'array',
            'audience_team_ids' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
    public function recipients(): HasMany { return $this->hasMany(LeagueNoticeRecipient::class); }
}
