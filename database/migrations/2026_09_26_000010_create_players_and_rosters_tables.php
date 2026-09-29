<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 180);
            $table->date('birth_date');
            $table->string('gender', 20)->default('unspecified');
            $table->string('position', 20);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('emergency_contact_name', 180);
            $table->string('emergency_contact_phone', 30);
            $table->string('emergency_contact_relationship', 80);
            $table->string('guardian_name', 180)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('photo_path');
            $table->string('photo_hash', 64);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['league_id', 'user_id']);
            $table->index(['league_id', 'full_name', 'birth_date']);
            $table->index(['league_id', 'photo_hash']);
        });

        Schema::create('player_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('path');
            $table->date('retain_until')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['player_id', 'type']);
        });

        Schema::create('player_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_participation_id')->constrained()->restrictOnDelete();
            $table->foreignId('competition_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('jersey_number');
            $table->string('status', 20)->default('pending')->index();
            $table->timestampTz('requested_at');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_reason', 500)->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'team_participation_id']);
            $table->index(['season_id', 'status']);
            $table->index(['team_participation_id', 'status']);
        });

        Schema::create('player_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->foreignId('player_registration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('type', 30);
            $table->string('reason', 500);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['player_id', 'occurred_at']);
        });

        DB::statement("CREATE UNIQUE INDEX player_registrations_one_active_per_season ON player_registrations (player_id, season_id) WHERE status IN ('pending', 'active', 'suspended')");
        DB::statement("CREATE UNIQUE INDEX player_registrations_jersey_unique ON player_registrations (team_participation_id, jersey_number) WHERE status IN ('pending', 'active', 'suspended')");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE players ADD CONSTRAINT players_status_check CHECK (status IN ('pending', 'active', 'suspended', 'inactive'))");
            DB::statement("ALTER TABLE players ADD CONSTRAINT players_gender_check CHECK (gender IN ('male', 'female', 'unspecified'))");
            DB::statement("ALTER TABLE players ADD CONSTRAINT players_position_check CHECK (position IN ('goalkeeper', 'defender', 'midfielder', 'forward'))");
            DB::statement("ALTER TABLE player_registrations ADD CONSTRAINT player_registrations_status_check CHECK (status IN ('pending', 'active', 'rejected', 'suspended', 'released'))");
            DB::statement('ALTER TABLE player_registrations ADD CONSTRAINT player_registrations_jersey_check CHECK (jersey_number BETWEEN 0 AND 999)');
            DB::statement("ALTER TABLE player_movements ADD CONSTRAINT player_movements_type_check CHECK (type IN ('registration_requested', 'approved', 'rejected', 'released', 'transferred', 'suspended', 'reactivated'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_movements');
        Schema::dropIfExists('player_registrations');
        Schema::dropIfExists('player_documents');
        Schema::dropIfExists('players');
    }
};
