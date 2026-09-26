<?php

namespace App\Domain\Competition\Models;

use App\Domain\Competition\Enums\SeasonStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tournament extends Model
{
    use SoftDeletes;

    protected $fillable = ['season_id', 'name', 'slug', 'status', 'created_by'];

    protected function casts(): array { return ['status' => SeasonStatus::class]; }

    public function season(): BelongsTo { return $this->belongsTo(Season::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function competitions(): HasMany { return $this->hasMany(Competition::class); }
}
