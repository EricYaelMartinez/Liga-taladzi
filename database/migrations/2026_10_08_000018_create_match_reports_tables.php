<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->cascadeOnDelete();
            $table->string('folio', 60)->nullable()->unique();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedSmallInteger('home_score')->default(0);
            $table->unsignedSmallInteger('away_score')->default(0);
            $table->boolean('home_no_show')->default(false);
            $table->boolean('away_no_show')->default(false);
            $table->text('summary')->nullable();
            $table->text('incidents')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('match_report_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_participation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('player_registration_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('related_player_registration_id')->nullable()->constrained('player_registrations')->restrictOnDelete();
            $table->string('type', 30);
            $table->unsignedSmallInteger('minute')->nullable();
            $table->string('period', 20)->default('match');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['match_report_id', 'type', 'minute']);
        });

        Schema::create('match_report_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_participation_id')->constrained()->restrictOnDelete();
            $table->foreignId('representative_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['match_report_id', 'team_participation_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE match_reports ADD CONSTRAINT match_reports_status_check CHECK (status IN ('draft', 'awaiting_confirmations', 'correction_required', 'under_review', 'approved'))");
            DB::statement('ALTER TABLE match_reports ADD CONSTRAINT match_reports_score_check CHECK (home_score BETWEEN 0 AND 999 AND away_score BETWEEN 0 AND 999)');
            DB::statement("ALTER TABLE match_report_events ADD CONSTRAINT match_report_events_type_check CHECK (type IN ('goal', 'own_goal', 'yellow_card', 'second_yellow', 'red_card', 'substitution', 'injury', 'incident'))");
            DB::statement("ALTER TABLE match_report_events ADD CONSTRAINT match_report_events_period_check CHECK (period IN ('first_half', 'halftime', 'second_half', 'extra_time', 'penalties', 'post_match', 'match'))");
            DB::statement("ALTER TABLE match_report_confirmations ADD CONSTRAINT match_report_confirmations_decision_check CHECK (decision IN ('pending', 'confirmed', 'rejected'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_report_confirmations');
        Schema::dropIfExists('match_report_events');
        Schema::dropIfExists('match_reports');
    }
};
