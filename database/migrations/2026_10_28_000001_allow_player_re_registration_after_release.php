<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve released/rejected registrations and their credentials, lineups and audit history.
        // The pre-existing player_registrations_one_active_per_season partial unique index
        // continues preventing duplicate pending/active/suspended registrations in a season.
        Schema::table('player_registrations', function ($table): void {
            $table->dropUnique('player_registrations_player_id_team_participation_id_unique');
        });

        DB::statement('CREATE INDEX player_registrations_player_participation_history_idx ON player_registrations (player_id, team_participation_id)');
    }

    public function down(): void
    {
        // A rollback cannot recreate the former unique constraint when historical
        // re-registrations exist. Refuse rather than delete user data.
        $hasDuplicates = DB::table('player_registrations')
            ->select('player_id', 'team_participation_id')
            ->groupBy('player_id', 'team_participation_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('No se puede restaurar la restricción anterior sin eliminar inscripciones históricas.');
        }

        DB::statement('DROP INDEX IF EXISTS player_registrations_player_participation_history_idx');
        Schema::table('player_registrations', function ($table): void {
            $table->unique(['player_id', 'team_participation_id']);
        });
    }
};
