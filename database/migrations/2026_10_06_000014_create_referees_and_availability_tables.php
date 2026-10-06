<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('photo_path')->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('category_level', 100);
            $table->string('status', 20)->default('active')->index();
            $table->date('joined_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['league_id', 'user_id']);
            $table->index(['league_id', 'category_level']);
        });

        Schema::create('referee_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->index(['referee_id', 'weekday']);
        });

        Schema::create('referee_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referee_id')->constrained()->cascadeOnDelete();
            $table->date('observed_on');
            $table->text('observation');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['referee_id', 'observed_on']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE referees ADD CONSTRAINT referees_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement('ALTER TABLE referee_availabilities ADD CONSTRAINT referee_availabilities_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
            DB::statement('ALTER TABLE referee_availabilities ADD CONSTRAINT referee_availabilities_time_check CHECK (starts_at < ends_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referee_observations');
        Schema::dropIfExists('referee_availabilities');
        Schema::dropIfExists('referees');
    }
};
