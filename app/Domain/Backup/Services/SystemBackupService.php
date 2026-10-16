<?php

namespace App\Domain\Backup\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Backup\Models\BackupRun;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

class SystemBackupService
{
    public function create(string $trigger = 'scheduled', ?int $userId = null, ?BackupRun $run = null): BackupRun
    {
        $run ??= new BackupRun();
        $run->fill(['requested_by' => $userId, 'trigger' => $trigger, 'status' => 'running', 'started_at' => now(), 'failure_message' => null]);
        $run->save();

        $directory = $this->backupDirectory();
        $temporary = $directory.'/.tmp-'.$run->id.'-'.bin2hex(random_bytes(5));
        $absolutePath = null;
        File::ensureDirectoryExists($temporary);

        try {
            $databasePath = $temporary.'/database.dump';
            $this->databaseDump($databasePath);

            $filename = 'liga-taladzi-'.now()->format('Ymd-His').'-'.$run->id.'.zip';
            $absolutePath = $directory.'/'.$filename;
            $this->buildArchive($absolutePath, $databasePath, $run);

            $run->update([
                'status' => 'completed',
                'filename' => $filename,
                'path' => $this->relativePath($filename),
                'checksum' => hash_file('sha256', $absolutePath),
                'size_bytes' => filesize($absolutePath),
                'completed_at' => now(),
            ]);

            $this->audit('backup.completed', $run, [
                'trigger' => $trigger,
                'filename' => $filename,
                'size_bytes' => $run->size_bytes,
                'checksum' => $run->checksum,
            ], $userId);
            $this->prune();

            return $run->fresh('requester');
        } catch (Throwable $exception) {
            if ($absolutePath) File::delete($absolutePath);
            $run->update([
                'status' => 'failed',
                'failure_message' => str($exception->getMessage())->limit(2000)->toString(),
                'completed_at' => now(),
            ]);
            $this->audit('backup.failed', $run, ['trigger' => $trigger, 'message' => $run->failure_message], $userId);
            throw $exception;
        } finally {
            File::deleteDirectory($temporary);
        }
    }

    public function restore(BackupRun $run): void
    {
        if (! $run->isCompleted()) throw new RuntimeException('El respaldo seleccionado no está completo.');

        $archive = storage_path('app/private/'.$run->path);
        if (! is_file($archive)) throw new RuntimeException('El archivo del respaldo ya no existe.');
        if (! hash_equals((string) $run->checksum, (string) hash_file('sha256', $archive))) {
            throw new RuntimeException('El respaldo no superó la verificación de integridad SHA-256.');
        }

        $temporary = $this->backupDirectory().'/.restore-'.$run->id.'-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($temporary);

        try {
            $zip = new ZipArchive();
            if ($zip->open($archive) !== true || ! $zip->extractTo($temporary)) {
                throw new RuntimeException('No fue posible abrir el respaldo.');
            }
            $zip->close();

            $databasePath = $temporary.'/database.dump';
            if (! is_file($databasePath)) throw new RuntimeException('El respaldo no contiene la base de datos.');
            $this->databaseRestore($databasePath);
            $this->restoreDirectory($temporary.'/files/private', storage_path('app/private'), ['backups']);
            $this->restoreDirectory($temporary.'/files/public', storage_path('app/public'));
        } finally {
            File::deleteDirectory($temporary);
        }
    }

    public function absolutePath(BackupRun $run): string
    {
        return storage_path('app/private/'.$run->path);
    }

    public function prune(): int
    {
        $keep = max(1, (int) config('backups.retention_count', 8));
        $expired = BackupRun::query()->where('status', 'completed')->latest('completed_at')->get()->slice($keep);
        foreach ($expired as $run) {
            if ($run->path) File::delete(storage_path('app/private/'.$run->path));
            $run->delete();
        }
        return $expired->count();
    }

    private function databaseDump(string $target): void
    {
        $connection = config('database.connections.pgsql');
        $this->run([
            'pg_dump', '--format=custom', '--no-owner', '--no-privileges',
            '--host='.(string) $connection['host'], '--port='.(string) $connection['port'],
            '--username='.(string) $connection['username'], '--file='.$target,
            (string) $connection['database'],
        ], ['PGPASSWORD' => (string) $connection['password']]);
    }

    private function databaseRestore(string $source): void
    {
        $connection = config('database.connections.pgsql');
        $this->run([
            'pg_restore', '--clean', '--if-exists', '--no-owner', '--no-privileges',
            '--host='.(string) $connection['host'], '--port='.(string) $connection['port'],
            '--username='.(string) $connection['username'], '--dbname='.(string) $connection['database'],
            $source,
        ], ['PGPASSWORD' => (string) $connection['password']]);
    }

    private function buildArchive(string $target, string $databasePath, BackupRun $run): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear el archivo del respaldo.');
        }
        $zip->addFile($databasePath, 'database.dump');
        $zip->addFromString('manifest.json', json_encode([
            'application' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'backup_id' => $run->id,
            'database' => config('database.connections.pgsql.database'),
            'includes' => ['database', 'private_uploads', 'public_uploads'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->addDirectory($zip, storage_path('app/private'), 'files/private', [$this->backupDirectory()]);
        $this->addDirectory($zip, storage_path('app/public'), 'files/public');
        if (! $zip->close()) throw new RuntimeException('No fue posible finalizar el archivo del respaldo.');
    }

    private function addDirectory(ZipArchive $zip, string $source, string $prefix, array $excluded = []): void
    {
        if (! is_dir($source)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (! $file->isFile() || collect($excluded)->contains(fn (string $item) => str_starts_with($path, $item.DIRECTORY_SEPARATOR))) continue;
            $relative = ltrim(str_replace('\\', '/', substr($path, strlen($source))), '/');
            $zip->addFile($path, $prefix.'/'.$relative);
        }
    }

    private function restoreDirectory(string $source, string $target, array $excludedNames = []): void
    {
        if (! is_dir($source)) return;
        File::ensureDirectoryExists($target);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = ltrim(substr($file->getPathname(), strlen($source)), DIRECTORY_SEPARATOR);
            if (collect($excludedNames)->contains(fn (string $name) => str_starts_with($relative, $name.DIRECTORY_SEPARATOR))) continue;
            $destination = $target.DIRECTORY_SEPARATOR.$relative;
            if ($file->isDir()) File::ensureDirectoryExists($destination);
            else {
                File::ensureDirectoryExists(dirname($destination));
                File::copy($file->getPathname(), $destination);
            }
        }
    }

    private function run(array $command, array $environment = []): void
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), $environment));
        if (! is_resource($process)) throw new RuntimeException('No fue posible iniciar la herramienta de respaldo.');
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) throw new RuntimeException(trim($error ?: $output ?: 'La herramienta de respaldo terminó con error.'));
    }

    private function audit(string $action, BackupRun $run, array $values, ?int $userId): void
    {
        AuditLog::create([
            'actor_user_id' => $userId,
            'action' => $action,
            'auditable_type' => $run->getMorphClass(),
            'auditable_id' => $run->id,
            'new_values' => $values,
            'created_at' => now(),
        ]);
    }

    private function backupDirectory(): string
    {
        $path = storage_path('app/private/'.trim((string) config('backups.directory', 'backups'), '/'));
        File::ensureDirectoryExists($path);
        return $path;
    }

    private function relativePath(string $filename): string
    {
        return trim((string) config('backups.directory', 'backups'), '/').'/'.$filename;
    }
}
