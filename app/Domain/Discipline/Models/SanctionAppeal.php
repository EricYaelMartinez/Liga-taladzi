<?php

namespace App\Domain\Discipline\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionAppeal extends Model
{
    protected $fillable = ['sanction_id', 'appellant_user_id', 'statement', 'evidence_path', 'evidence_original_name', 'evidence_mime', 'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'resolution'];
    protected $hidden = ['evidence_path'];
    protected function casts(): array { return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime']; }
    public function sanction(): BelongsTo { return $this->belongsTo(Sanction::class); }
    public function appellant(): BelongsTo { return $this->belongsTo(User::class, 'appellant_user_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
