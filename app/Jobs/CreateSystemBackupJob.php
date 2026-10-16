<?php

namespace App\Jobs;

use App\Domain\Backup\Models\BackupRun;
use App\Domain\Backup\Services\SystemBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateSystemBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public readonly int $backupRunId) {}

    public function handle(SystemBackupService $backups): void
    {
        $run = BackupRun::findOrFail($this->backupRunId);
        $backups->create($run->trigger, $run->requested_by, $run);
    }

    public function failed(?Throwable $exception): void
    {
        BackupRun::query()->whereKey($this->backupRunId)->where('status', '!=', 'completed')->update([
            'status' => 'failed',
            'failure_message' => str($exception?->getMessage() ?? 'La tarea de respaldo fue interrumpida.')->limit(2000)->toString(),
            'completed_at' => now(),
        ]);
    }
}
