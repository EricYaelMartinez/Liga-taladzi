<?php

namespace App\Console\Commands;

use App\Domain\Scheduling\Services\MatchLifecycleService;
use Illuminate\Console\Command;

class SyncMatchLifecycle extends Command
{
    protected $signature = 'liga:sync-match-lifecycle';

    protected $description = 'Inicia partidos programados y cierra jornadas terminales según sus horarios y resultados';

    public function handle(MatchLifecycleService $lifecycle): int
    {
        $result = $lifecycle->syncAll();
        $this->info("Partidos iniciados: {$result['started']}; jornadas cerradas: {$result['closed_matchdays']}.");

        return self::SUCCESS;
    }
}
