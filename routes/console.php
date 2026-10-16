<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('liga:info', function (): void {
    $this->info('Liga Taladzi: entorno base disponible.');
})->purpose('Verifica que los comandos del proyecto están disponibles.');

Schedule::command('liga:sync-match-lifecycle')->everyMinute()->withoutOverlapping();
Schedule::command('liga:sync-finance-lifecycle')->hourly()->withoutOverlapping();

$backupDays = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
$backupDay = $backupDays[strtolower((string) config('backups.schedule_day', 'sunday'))] ?? 0;
Schedule::command('liga:backup --trigger=scheduled')
    ->weeklyOn($backupDay, (string) config('backups.schedule_time', '03:00'))
    ->withoutOverlapping(360)
    ->onOneServer();

