import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_16_000022_create_backup_runs_table.php',
    'database/migrations/2026_10_16_000023_create_failed_jobs_table.php',
    'app/Domain/Backup/Models/BackupRun.php',
    'app/Domain/Backup/Services/SystemBackupService.php',
    'app/Console/Commands/CreateSystemBackup.php',
    'app/Console/Commands/RestoreSystemBackup.php',
    'app/Jobs/CreateSystemBackupJob.php',
    'app/Http/Controllers/Admin/BackupController.php',
    'app/Http/Controllers/League/AuditLogController.php',
    'resources/js/pages/Admin/Backups/Index.vue',
    'resources/js/pages/League/Audit/Index.vue',
    'tests/Feature/Admin/BackupManagementTest.php',
    'tests/Feature/League/AuditManagementTest.php',
    'MODULO_19_VERIFICACION.md',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const backup = readFileSync('app/Domain/Backup/Services/SystemBackupService.php', 'utf8');
for (const rule of ['pg_dump', 'pg_restore', "hash_file('sha256'", "'backup.completed'", 'private_uploads', 'public_uploads', 'prune()']) {
    if (!backup.includes(rule)) throw new Error(`Falta una protección de respaldo: ${rule}`);
}

const restore = readFileSync('app/Console/Commands/RestoreSystemBackup.php', 'utf8');
for (const rule of ["{--force", "create('pre_restore')", "Artisan::call('down'", "Artisan::call('up'"]) {
    if (!restore.includes(rule)) throw new Error(`Falta una protección de restauración: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["'/respaldos'", "'/respaldos/{backup}/descargar'", 'permission:backups.manage', "'/bitacora/exportar'"]) {
    if (!routes.includes(route)) throw new Error(`Falta una ruta protegida: ${route}`);
}

const schedule = readFileSync('routes/console.php', 'utf8');
if (!schedule.includes("liga:backup --trigger=scheduled") || !schedule.includes('weeklyOn')) throw new Error('Falta el respaldo semanal programado.');

const compose = readFileSync('compose.yaml', 'utf8');
if (!compose.includes('queue:') || !compose.includes('queue:work')) throw new Error('Falta el trabajador para respaldos manuales en segundo plano.');

const seed = readFileSync('database/seeders/IdentitySeeder.php', 'utf8');
if (!seed.includes("'backups.manage'")) throw new Error('Falta el permiso exclusivo de respaldos.');

const audit = readFileSync('app/Http/Controllers/League/AuditLogController.php', 'utf8');
for (const rule of ["where('league_id', $leagueId)", 'streamDownload', "'date_from'", "'ip_address'"]) {
    if (!audit.includes(rule)) throw new Error(`Falta una regla de auditoría: ${rule}`);
}

console.log('Validación estructural del Módulo 19 completada.');
