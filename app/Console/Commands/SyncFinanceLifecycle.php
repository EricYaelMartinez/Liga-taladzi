<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\FinanceService;
use Illuminate\Console\Command;

class SyncFinanceLifecycle extends Command
{
    protected $signature = 'liga:sync-finance-lifecycle';

    protected $description = 'Actualiza prórrogas, suspensiones, reactivaciones y exclusiones por adeudos';

    public function handle(FinanceService $finance): int
    {
        $changed = $finance->evaluateAll();
        $this->info("Participaciones financieras actualizadas: {$changed}.");
        return self::SUCCESS;
    }
}
