<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Competition\Models\Division;
use App\Domain\Scheduling\Enums\FieldStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayingField extends Model
{
    use SoftDeletes;

    protected $fillable = ['venue_id', 'name', 'surface', 'has_lighting', 'capacity', 'notes', 'status'];

    protected function casts(): array
    {
        return ['has_lighting' => 'boolean', 'capacity' => 'integer', 'status' => FieldStatus::class];
    }

    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function divisions(): BelongsToMany { return $this->belongsToMany(Division::class, 'field_division')->withTimestamps(); }
    public function availabilities(): HasMany { return $this->hasMany(FieldAvailability::class); }
    public function blocks(): HasMany { return $this->hasMany(FieldBlock::class); }
}
