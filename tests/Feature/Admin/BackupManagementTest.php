<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Backup\Models\BackupRun;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Jobs\CreateSystemBackupJob;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    public function test_only_system_administrator_can_open_backup_management(): void
    {
        $admin = $this->systemAdministrator();
        $this->actingAs($admin)->get('/administracion/respaldos')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Backups/Index')->has('schedule')->has('backups'));

        $regular = User::factory()->create();
        $this->actingAs($regular)->get('/administracion/respaldos')->assertForbidden();
        $this->actingAs($regular)->post('/administracion/respaldos')->assertForbidden();
    }

    public function test_administrator_can_request_a_manual_backup(): void
    {
        $admin = $this->systemAdministrator();
        Queue::fake();

        $this->actingAs($admin)->post('/administracion/respaldos')->assertRedirect('/administracion/respaldos')->assertSessionHas('success');
        $run = BackupRun::firstOrFail();
        $this->assertSame('queued', $run->status);
        Queue::assertPushed(CreateSystemBackupJob::class, fn (CreateSystemBackupJob $job) => $job->backupRunId === $run->id);
    }

    public function test_completed_backup_download_is_private_and_audited(): void
    {
        $admin = $this->systemAdministrator();
        $relative = 'backups/test-download.zip';
        $absolute = storage_path('app/private/'.$relative);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, 'backup-content');
        $run = BackupRun::create(['requested_by' => $admin->id, 'trigger' => 'manual', 'status' => 'completed', 'filename' => 'test-download.zip', 'path' => $relative, 'checksum' => hash_file('sha256', $absolute), 'size_bytes' => filesize($absolute), 'started_at' => now(), 'completed_at' => now()]);

        $this->actingAs($admin)->get("/administracion/respaldos/{$run->id}/descargar")->assertOk()->assertDownload('test-download.zip');
        $this->assertTrue(AuditLog::where('action', 'backup.downloaded')->where('auditable_id', $run->id)->exists());
        File::delete($absolute);
    }

    private function systemAdministrator(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'system_admin')->firstOrFail());
        return $user;
    }
}
