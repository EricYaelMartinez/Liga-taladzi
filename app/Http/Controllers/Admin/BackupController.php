<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Backup\Models\BackupRun;
use App\Domain\Backup\Services\SystemBackupService;
use App\Http\Controllers\Controller;
use App\Jobs\CreateSystemBackupJob;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly SystemBackupService $backups,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Backups/Index', [
            'backups' => BackupRun::query()->with('requester:id,name,email')->latest('started_at')->paginate(20),
            'schedule' => [
                'day' => config('backups.schedule_day'),
                'time' => config('backups.schedule_time'),
                'retention' => (int) config('backups.retention_count'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (BackupRun::whereIn('status', ['queued', 'running'])->exists()) {
            return to_route('admin.backups.index')->with('error', 'Ya existe un respaldo en espera o en proceso.');
        }

        $run = BackupRun::create(['requested_by' => $request->user()->id, 'trigger' => 'manual', 'status' => 'queued', 'started_at' => now()]);
        CreateSystemBackupJob::dispatch($run->id);

        return to_route('admin.backups.index')->with('success', 'El respaldo fue enviado a segundo plano. Puedes consultar su avance en el historial.');
    }

    public function download(Request $request, BackupRun $backup): BinaryFileResponse
    {
        abort_unless($backup->isCompleted(), 404);
        $path = $this->backups->absolutePath($backup);
        abort_unless(is_file($path), 404);

        $this->audit->log($request, 'backup.downloaded', $backup, null, [], [
            'filename' => $backup->filename,
            'checksum' => $backup->checksum,
        ]);

        return response()->download($path, $backup->filename, ['Content-Type' => 'application/zip']);
    }
}
