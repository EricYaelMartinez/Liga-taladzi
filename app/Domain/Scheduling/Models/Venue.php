<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\League\Models\League;
use App\Domain\Scheduling\Enums\VenueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = ['league_id', 'name', 'address', 'latitude', 'longitude', 'contact_name', 'phone', 'notes', 'status'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'status' => VenueStatus::class];
    }

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function fields(): HasMany { return $this->hasMany(PlayingField::class); }
}
