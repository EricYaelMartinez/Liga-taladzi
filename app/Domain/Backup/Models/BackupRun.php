<?php

namespace App\Domain\Backup\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    protected $fillable = [
        'requested_by', 'trigger', 'status', 'filename', 'path', 'checksum',
        'size_bytes', 'started_at', 'completed_at', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' && filled($this->path);
    }
}
