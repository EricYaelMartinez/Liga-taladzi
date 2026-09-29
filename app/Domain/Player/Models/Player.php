<?php

namespace App\Domain\Player\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Player\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'league_id', 'user_id', 'full_name', 'birth_date', 'gender', 'position',
        'phone', 'email', 'emergency_contact_name', 'emergency_contact_phone',
        'emergency_contact_relationship', 'guardian_name', 'guardian_phone',
        'photo_path', 'photo_hash', 'status', 'created_by',
    ];

    protected $hidden = ['photo_path', 'photo_hash'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'status' => PlayerStatus::class];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function documents(): HasMany { return $this->hasMany(PlayerDocument::class); }
    public function registrations(): HasMany { return $this->hasMany(PlayerRegistration::class); }
    public function movements(): HasMany { return $this->hasMany(PlayerMovement::class); }
}
