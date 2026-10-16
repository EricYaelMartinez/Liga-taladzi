<?php

namespace App\Domain\Notification\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueNoticeRecipient extends Model
{
    protected $fillable = ['league_notice_id', 'user_id', 'delivered_at', 'read_at'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function notice(): BelongsTo { return $this->belongsTo(LeagueNotice::class, 'league_notice_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
