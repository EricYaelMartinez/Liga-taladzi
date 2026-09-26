<?php

namespace App\Domain\Competition\Models;

use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Division extends Model
{
    use SoftDeletes;

    protected $fillable = ['league_id', 'name', 'slug', 'sort_order', 'status'];

    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function competitions(): HasMany { return $this->hasMany(Competition::class); }
}
