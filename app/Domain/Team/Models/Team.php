<?php

namespace App\Domain\Team\Models;

use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\Team\Enums\TeamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'league_id', 'name', 'short_name', 'slug', 'crest_path', 'photo_path',
        'primary_color', 'secondary_color', 'phone', 'email', 'founded_on',
        'description', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['status' => TeamStatus::class, 'founded_on' => 'date'];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function names(): HasMany { return $this->hasMany(TeamNameHistory::class)->latest('valid_from'); }
    public function representatives(): HasMany { return $this->hasMany(TeamRepresentative::class); }
    public function activeRepresentative(): HasOne { return $this->hasOne(TeamRepresentative::class)->where('status', 'active'); }
    public function participations(): HasMany { return $this->hasMany(TeamParticipation::class); }
    public function changeRequests(): HasMany { return $this->hasMany(TeamChangeRequest::class); }
}
