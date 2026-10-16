<?php

namespace App\Console\Commands;

use App\Domain\Backup\Services\SystemBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateSystemBackup extends Command
{
    protected $signature = 'liga:backup {--trigger=manual} {--user=}';
    protected $description = 'Crea un respaldo completo de PostgreSQL y archivos cargados';

    public function handle(SystemBackupService $backups): int
    {
        try {
            $run = $backups->create((string) $this->option('trigger'), $this->option('user') ? (int) $this->option('user') : null);
            $this->info("Respaldo {$run->filename} creado y verificado correctamente.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('El respaldo falló: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
