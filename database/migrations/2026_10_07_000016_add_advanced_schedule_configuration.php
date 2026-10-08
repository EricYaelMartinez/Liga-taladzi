<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('default_max_matches_per_field_day')->default(6);
        });
        Schema::table('playing_fields', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_matches_per_day')->nullable();
        });
        Schema::table('matchdays', function (Blueprint $table): void {
            $table->unsignedSmallInteger('leg_number')->default(1);
            $table->unsignedSmallInteger('generation_round')->nullable();
            $table->boolean('generated_automatically')->default(false);
            $table->unique(['competition_id', 'generation_round']);
        });
        Schema::table('matches', function (Blueprint $table): void {
            $table->unsignedSmallInteger('leg_number')->default(1);
            $table->string('pairing_key', 50)->nullable();
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->unique(['competition_id', 'leg_number', 'pairing_key'], 'matches_unique_pairing_per_leg');
        });

        Schema::create('schedule_time_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playing_field_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['league_id', 'playing_field_id', 'weekday', 'starts_at'], 'schedule_slots_unique');
            $table->index(['league_id', 'weekday', 'is_active']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_time_slots ADD CONSTRAINT schedule_time_slots_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
            DB::statement('ALTER TABLE match_schedule_changes DROP CONSTRAINT IF EXISTS match_schedule_change_type_check');
            DB::statement("ALTER TABLE match_schedule_changes ADD CONSTRAINT match_schedule_change_type_check CHECK (type IN ('scheduled', 'rescheduled', 'referees_assigned', 'status_changed', 'cancelled'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE match_schedule_changes DROP CONSTRAINT IF EXISTS match_schedule_change_type_check');
            DB::table('match_schedule_changes')->where('type', 'referees_assigned')->update(['type' => 'status_changed']);
            DB::statement("ALTER TABLE match_schedule_changes ADD CONSTRAINT match_schedule_change_type_check CHECK (type IN ('scheduled', 'rescheduled', 'status_changed', 'cancelled'))");
        }
        Schema::dropIfExists('schedule_time_slots');
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropUnique('matches_unique_pairing_per_leg');
            $table->dropColumn(['leg_number', 'pairing_key']);
        });
        Schema::table('matchdays', function (Blueprint $table): void {
            $table->dropUnique(['competition_id', 'generation_round']);
            $table->dropColumn(['leg_number', 'generation_round', 'generated_automatically']);
        });
        Schema::table('playing_fields', fn (Blueprint $table) => $table->dropColumn('max_matches_per_day'));
        Schema::table('league_settings', fn (Blueprint $table) => $table->dropColumn('default_max_matches_per_field_day'));
    }
};
