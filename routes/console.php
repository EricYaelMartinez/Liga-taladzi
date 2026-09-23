<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('liga:info', function (): void {
    $this->info('Liga Taladzi: entorno base disponible.');
})->purpose('Verifica que los comandos del proyecto están disponibles.');

