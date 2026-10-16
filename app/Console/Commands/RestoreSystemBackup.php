<?php

namespace App\Console\Commands;

use App\Domain\Backup\Models\BackupRun;
use App\Domain\Backup\Services\SystemBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class RestoreSystemBackup extends Command
{
    protected $signature = 'liga:backup-restore {backup : ID del respaldo} {--force : Confirma la sobrescritura de base de datos y archivos}';
    protected $description = 'Restaura un respaldo completo con validación de integridad';

    public function handle(SystemBackupService $backups): int
    {
        if (! $this->option('force')) {
            $this->error('Operación cancelada. Debes agregar --force después de comprobar el respaldo y detener el acceso de usuarios.');
            return self::FAILURE;
        }

        $run = BackupRun::findOrFail((int) $this->argument('backup'));
        $this->warn("Se restaurará {$run->filename}. La información actual será reemplazada.");
        $safety = $backups->create('pre_restore');
        $this->info("Respaldo de seguridad previo creado: {$safety->filename}.");

        Artisan::call('down', ['--retry' => 60]);
        try {
            $backups->restore($run);
            $this->info('Restauración terminada correctamente.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('La restauración falló: '.$exception->getMessage());
            return self::FAILURE;
        } finally {
            Artisan::call('up');
        }
    }
}
