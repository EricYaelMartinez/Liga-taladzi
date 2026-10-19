<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $activeSeasonIds = DB::table('seasons')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select('id');

        DB::table('tournaments')
            ->whereIn('season_id', $activeSeasonIds)
            ->whereNull('deleted_at')
            ->update(['status' => 'active', 'updated_at' => now()]);

        $activeTournamentIds = DB::table('tournaments')
            ->whereIn('season_id', DB::table('seasons')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->select('id'))
            ->whereNull('deleted_at')
            ->select('id');

        DB::table('competitions')
            ->whereIn('tournament_id', $activeTournamentIds)
            ->whereNull('deleted_at')
            ->update(['status' => 'active', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // No se revierten estados porque podrían contener actividad deportiva posterior.
    }
};
