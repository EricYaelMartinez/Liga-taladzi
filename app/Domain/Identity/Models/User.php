<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'password',
        'force_password_change',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'force_password_change' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function leagueMemberships(): HasMany
    {
        return $this->hasMany(\App\Domain\League\Models\LeagueMembership::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function hasLeaguePermission(string $permission, int $leagueId, int $roleId): bool
    {
        return $this->leagueMemberships()
            ->where('league_id', $leagueId)
            ->where('role_id', $roleId)
            ->where('status', 'active')
            ->whereHas('league', fn ($query) => $query->where('status', 'active'))
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
