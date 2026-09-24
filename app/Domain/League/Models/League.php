<?php

namespace App\Domain\League\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Enums\LeagueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class League extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'logo_path', 'primary_color', 'secondary_color',
        'status', 'settings', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeagueStatus::class,
            'settings' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    public function isActive(): bool
    {
        return $this->status === LeagueStatus::Active;
    }
}
