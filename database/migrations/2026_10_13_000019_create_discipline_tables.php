<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('automatic_sanctions_enabled')->default(true);
            $table->unsignedSmallInteger('yellow_card_threshold')->default(5);
            $table->unsignedSmallInteger('yellow_suspension_matches')->default(1);
            $table->unsignedSmallInteger('second_yellow_suspension_matches')->default(1);
            $table->unsignedSmallInteger('red_card_suspension_matches')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sanctions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('competition_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('subject_type', 20);
            $table->string('subject_name', 180);
            $table->string('kind', 30);
            $table->string('source', 20)->default('manual');
            $table->string('source_key', 150)->nullable();
            $table->foreignId('match_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->foreignId('source_event_id')->nullable()->constrained('match_report_events')->nullOnDelete();
            $table->text('reason');
            $table->text('legal_basis')->nullable();
            $table->unsignedSmallInteger('matches_total')->default(0);
            $table->unsignedSmallInteger('matches_served')->default(0);
            $table->unsignedSmallInteger('points_deducted')->default(0);
            $table->decimal('fine_amount', 12, 2)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestampTz('issued_at');
            $table->timestampTz('appeal_deadline_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'source_key']);
            $table->index(['league_id', 'subject_type', 'status']);
            $table->index(['player_id', 'status']);
            $table->index(['team_id', 'competition_id', 'kind']);
        });

        Schema::create('sanction_served_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sanction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->restrictOnDelete();
            $table->timestampTz('served_at');
            $table->timestamps();
            $table->unique(['sanction_id', 'match_id']);
        });

        Schema::create('sanction_appeals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sanction_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('appellant_user_id')->constrained('users')->restrictOnDelete();
            $table->text('statement');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_original_name')->nullable();
            $table->string('evidence_mime', 100)->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->timestampTz('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        DB::table('leagues')->orderBy('id')->get()->each(function (object $league): void {
            DB::table('discipline_policies')->insert([
                'league_id' => $league->id,
                'automatic_sanctions_enabled' => true,
                'yellow_card_threshold' => 5,
                'yellow_suspension_matches' => 1,
                'second_yellow_suspension_matches' => 1,
                'red_card_suspension_matches' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sanctions ADD CONSTRAINT sanctions_subject_type_check CHECK (subject_type IN ('player', 'team', 'staff'))");
            DB::statement("ALTER TABLE sanctions ADD CONSTRAINT sanctions_kind_check CHECK (kind IN ('suspension', 'points_deduction', 'fine', 'warning', 'disqualification'))");
            DB::statement("ALTER TABLE sanctions ADD CONSTRAINT sanctions_source_check CHECK (source IN ('automatic', 'manual'))");
            DB::statement("ALTER TABLE sanctions ADD CONSTRAINT sanctions_status_check CHECK (status IN ('active', 'completed', 'revoked'))");
            DB::statement("ALTER TABLE sanction_appeals ADD CONSTRAINT sanction_appeals_status_check CHECK (status IN ('submitted', 'upheld', 'reduced', 'revoked'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_appeals');
        Schema::dropIfExists('sanction_served_matches');
        Schema::dropIfExists('sanctions');
        Schema::dropIfExists('discipline_policies');
    }
};
