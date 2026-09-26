<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('registration_starts_at')->nullable();
            $table->timestampTz('registration_ends_at')->nullable();
            $table->string('status', 20)->default('planning')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['league_id', 'name']);
            $table->index(['league_id', 'status']);
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 110);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['league_id', 'slug']);
            $table->index(['league_id', 'sort_order']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 110);
            $table->unsignedSmallInteger('minimum_age')->nullable();
            $table->unsignedSmallInteger('maximum_age')->nullable();
            $table->string('gender', 20)->default('mixed');
            $table->text('requirements')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['league_id', 'slug']);
        });

        Schema::create('tournaments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 130);
            $table->string('status', 20)->default('planning')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['season_id', 'slug']);
        });

        Schema::create('regulations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('version');
            $table->string('status', 20)->default('draft')->index();
            $table->smallInteger('points_win')->default(3);
            $table->smallInteger('points_draw')->default(1);
            $table->smallInteger('points_loss')->default(0);
            $table->unsignedSmallInteger('walkover_home_goals')->default(3);
            $table->unsignedSmallInteger('walkover_away_goals')->default(0);
            $table->smallInteger('fair_play_yellow_points')->default(1);
            $table->smallInteger('fair_play_second_yellow_points')->default(2);
            $table->smallInteger('fair_play_red_points')->default(3);
            $table->json('league_settings_snapshot');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['league_id', 'name', 'version']);
            $table->index(['league_id', 'status']);
        });

        Schema::create('regulation_tiebreakers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->string('criterion', 40);
            $table->unsignedSmallInteger('priority');
            $table->unique(['regulation_id', 'criterion']);
            $table->unique(['regulation_id', 'priority']);
        });

        Schema::create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('regulation_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('format', 30);
            $table->unsignedSmallInteger('regular_leg_count')->default(1);
            $table->unsignedSmallInteger('knockout_leg_count')->default(2);
            $table->unsignedSmallInteger('minimum_teams')->nullable();
            $table->unsignedSmallInteger('maximum_teams')->nullable();
            $table->unsignedSmallInteger('minimum_roster_size');
            $table->unsignedSmallInteger('maximum_roster_size');
            $table->timestampTz('registration_starts_at')->nullable();
            $table->timestampTz('registration_ends_at')->nullable();
            $table->string('status', 20)->default('planning')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tournament_id', 'division_id', 'category_id'], 'competitions_scope_unique');
            $table->index(['tournament_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX seasons_league_name_ci_unique ON seasons (league_id, LOWER(name))');
        DB::statement('CREATE UNIQUE INDEX regulations_league_name_version_ci_unique ON regulations (league_id, LOWER(name), version)');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE seasons ADD CONSTRAINT seasons_dates_check CHECK (ends_on >= starts_on)");
            DB::statement("ALTER TABLE seasons ADD CONSTRAINT seasons_registration_check CHECK (registration_ends_at IS NULL OR registration_starts_at IS NULL OR registration_ends_at >= registration_starts_at)");
            DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_ages_check CHECK (maximum_age IS NULL OR minimum_age IS NULL OR maximum_age >= minimum_age)");
            DB::statement("ALTER TABLE competitions ADD CONSTRAINT competitions_team_limits_check CHECK (maximum_teams IS NULL OR minimum_teams IS NULL OR maximum_teams >= minimum_teams)");
            DB::statement("ALTER TABLE competitions ADD CONSTRAINT competitions_roster_limits_check CHECK (maximum_roster_size >= minimum_roster_size)");
            DB::statement("ALTER TABLE competitions ADD CONSTRAINT competitions_registration_check CHECK (registration_ends_at IS NULL OR registration_starts_at IS NULL OR registration_ends_at >= registration_starts_at)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('regulation_tiebreakers');
        Schema::dropIfExists('regulations');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('seasons');
    }
};
