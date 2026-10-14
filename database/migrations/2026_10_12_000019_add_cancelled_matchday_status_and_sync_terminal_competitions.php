<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE matchdays DROP CONSTRAINT IF EXISTS matchdays_status_check');
            DB::statement("ALTER TABLE matchdays ADD CONSTRAINT matchdays_status_check CHECK (status IN ('draft', 'published', 'completed', 'cancelled'))");
        }

        foreach (['finished', 'archived', 'cancelled'] as $status) {
            $seasonIds = DB::table('seasons')->where('status', $status)->select('id');
            DB::table('tournaments')->whereIn('season_id', $seasonIds)->update(['status' => $status, 'updated_at' => now()]);

            $tournamentIds = DB::table('tournaments')
                ->whereIn('season_id', DB::table('seasons')->where('status', $status)->select('id'))
                ->select('id');
            DB::table('competitions')->whereIn('tournament_id', $tournamentIds)->update(['status' => $status, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('matchdays')->where('status', 'cancelled')->update(['status' => 'completed']);
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE matchdays DROP CONSTRAINT IF EXISTS matchdays_status_check');
            DB::statement("ALTER TABLE matchdays ADD CONSTRAINT matchdays_status_check CHECK (status IN ('draft', 'published', 'completed'))");
        }
    }
};
