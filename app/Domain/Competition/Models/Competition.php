<?php

namespace App\Domain\Competition\Models;

use App\Domain\Competition\Enums\CompetitionFormat;
use App\Domain\Competition\Enums\SeasonStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Competition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tournament_id', 'division_id', 'category_id', 'regulation_id', 'name', 'format',
        'regular_leg_count', 'knockout_leg_count', 'minimum_teams', 'maximum_teams',
        'minimum_roster_size', 'maximum_roster_size', 'registration_starts_at',
        'registration_ends_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'format' => CompetitionFormat::class,
            'status' => SeasonStatus::class,
            'registration_starts_at' => 'datetime',
            'registration_ends_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }
    public function division(): BelongsTo { return $this->belongsTo(Division::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function regulation(): BelongsTo { return $this->belongsTo(Regulation::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
