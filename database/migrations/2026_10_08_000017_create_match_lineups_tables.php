<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_lineups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('team_participation_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'team_participation_id']);
            $table->index(['team_participation_id', 'status']);
        });

        Schema::create('match_lineup_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_lineup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_registration_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->unsignedSmallInteger('jersey_number');
            $table->string('position', 20);
            $table->boolean('is_captain')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['match_lineup_id', 'player_registration_id']);
            $table->index(['match_lineup_id', 'role', 'display_order']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE match_lineups ADD CONSTRAINT match_lineups_status_check CHECK (status IN ('draft', 'submitted'))");
            DB::statement("ALTER TABLE match_lineup_players ADD CONSTRAINT match_lineup_players_role_check CHECK (role IN ('starter', 'substitute'))");
            DB::statement('ALTER TABLE match_lineup_players ADD CONSTRAINT match_lineup_players_jersey_check CHECK (jersey_number BETWEEN 0 AND 999)');
            DB::statement('CREATE UNIQUE INDEX match_lineup_players_one_captain ON match_lineup_players (match_lineup_id) WHERE is_captain = true');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_lineup_players');
        Schema::dropIfExists('match_lineups');
    }
};
