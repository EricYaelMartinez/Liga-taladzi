<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('liga:info', function (): void {
    $this->info('Liga Taladzi: entorno base disponible.');
})->purpose('Verifica que los comandos del proyecto están disponibles.');

Schedule::command('liga:sync-match-lifecycle')->everyMinute()->withoutOverlapping();

