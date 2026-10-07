<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchdays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 120);
            $table->string('phase', 20)->default('regular')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['competition_id', 'number']);
        });

        Schema::create('matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_participation_id')->constrained('team_participations')->restrictOnDelete();
            $table->foreignId('away_team_participation_id')->constrained('team_participations')->restrictOnDelete();
            $table->foreignId('playing_field_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('scheduled_end_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 20)->default('draft')->index();
            $table->text('public_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['competition_id', 'status']);
            $table->index(['matchday_id', 'scheduled_at']);
            $table->index(['playing_field_id', 'scheduled_at', 'scheduled_end_at']);
        });

        Schema::create('match_referee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referee_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['match_id', 'role']);
            $table->unique(['match_id', 'referee_id']);
            $table->index(['referee_id', 'match_id']);
        });

        Schema::create('match_schedule_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 500);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->index(['match_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE matchdays ADD CONSTRAINT matchdays_phase_check CHECK (phase IN ('regular', 'group', 'knockout', 'custom'))");
            DB::statement("ALTER TABLE matchdays ADD CONSTRAINT matchdays_status_check CHECK (status IN ('draft', 'published', 'completed'))");
            DB::statement('ALTER TABLE matchdays ADD CONSTRAINT matchdays_dates_check CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)');
            DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('draft', 'scheduled', 'in_progress', 'finished', 'suspended', 'postponed', 'cancelled'))");
            DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_teams_check CHECK (home_team_participation_id <> away_team_participation_id)');
            DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_schedule_check CHECK ((scheduled_at IS NULL AND scheduled_end_at IS NULL) OR (scheduled_at IS NOT NULL AND scheduled_end_at > scheduled_at))');
            DB::statement("ALTER TABLE match_referee_assignments ADD CONSTRAINT match_referee_role_check CHECK (role IN ('central', 'assistant_1', 'assistant_2', 'fourth'))");
            DB::statement("ALTER TABLE match_schedule_changes ADD CONSTRAINT match_schedule_change_type_check CHECK (type IN ('scheduled', 'rescheduled', 'status_changed', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_schedule_changes');
        Schema::dropIfExists('match_referee_assignments');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('matchdays');
    }
};
