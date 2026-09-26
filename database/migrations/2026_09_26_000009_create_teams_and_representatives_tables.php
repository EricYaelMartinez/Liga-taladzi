<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('short_name', 30);
            $table->string('slug', 160);
            $table->string('crest_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('primary_color', 7)->default('#125444');
            $table->string('secondary_color', 7)->default('#ffffff');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->date('founded_on')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['league_id', 'slug']);
            $table->index(['league_id', 'status']);
        });

        Schema::create('team_name_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('short_name', 30);
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->index(['team_id', 'valid_from']);
        });

        Schema::create('team_representatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('league_membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('photo_path');
            $table->string('ine_path');
            $table->string('status', 20)->default('active')->index();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['team_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('team_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->string('registered_name', 150);
            $table->string('status', 20)->default('pending')->index();
            $table->timestampTz('requested_at');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_reason', 500)->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('reactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'season_id']);
            $table->index(['competition_id', 'status']);
            $table->index(['season_id', 'division_id', 'status']);
        });

        Schema::create('team_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->json('changes');
            $table->string('status', 20)->default('pending')->index();
            $table->string('request_reason', 500);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('review_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX team_representatives_one_active_per_team ON team_representatives (team_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX team_representatives_one_active_team_per_user ON team_representatives (user_id) WHERE status = 'active'");
        DB::statement('CREATE UNIQUE INDEX team_participations_name_scope_unique ON team_participations (season_id, division_id, LOWER(registered_name))');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE teams ADD CONSTRAINT teams_status_check CHECK (status IN ('pending', 'active', 'suspended', 'inactive', 'deregistered'))");
            DB::statement("ALTER TABLE team_representatives ADD CONSTRAINT team_representatives_status_check CHECK (status IN ('active', 'ended'))");
            DB::statement("ALTER TABLE team_participations ADD CONSTRAINT team_participations_status_check CHECK (status IN ('pending', 'active', 'rejected', 'suspended', 'inactive', 'deregistered'))");
            DB::statement("ALTER TABLE team_change_requests ADD CONSTRAINT team_change_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_change_requests');
        Schema::dropIfExists('team_participations');
        Schema::dropIfExists('team_representatives');
        Schema::dropIfExists('team_name_histories');
        Schema::dropIfExists('teams');
    }
};
