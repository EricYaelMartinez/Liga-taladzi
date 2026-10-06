<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['league_id', 'name']);
        });

        Schema::create('playing_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('surface', 30)->default('synthetic');
            $table->boolean('has_lighting')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['venue_id', 'name']);
        });

        Schema::create('field_division', function (Blueprint $table): void {
            $table->foreignId('playing_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['playing_field_id', 'division_id']);
        });

        Schema::create('field_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playing_field_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->index(['playing_field_id', 'weekday']);
        });

        Schema::create('field_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playing_field_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['playing_field_id', 'starts_at', 'ends_at']);
        });

        DB::statement('CREATE UNIQUE INDEX venues_league_name_lower_unique ON venues (league_id, LOWER(name)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX playing_fields_venue_name_lower_unique ON playing_fields (venue_id, LOWER(name)) WHERE deleted_at IS NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE venues ADD CONSTRAINT venues_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement("ALTER TABLE playing_fields ADD CONSTRAINT playing_fields_status_check CHECK (status IN ('active', 'maintenance', 'inactive'))");
            DB::statement("ALTER TABLE playing_fields ADD CONSTRAINT playing_fields_surface_check CHECK (surface IN ('natural_grass', 'synthetic', 'dirt', 'concrete', 'other'))");
            DB::statement("ALTER TABLE field_availabilities ADD CONSTRAINT field_availabilities_weekday_check CHECK (weekday BETWEEN 1 AND 7)");
            DB::statement('ALTER TABLE field_availabilities ADD CONSTRAINT field_availabilities_time_check CHECK (starts_at < ends_at)');
            DB::statement("ALTER TABLE field_blocks ADD CONSTRAINT field_blocks_type_check CHECK (type IN ('maintenance', 'unavailable', 'event'))");
            DB::statement('ALTER TABLE field_blocks ADD CONSTRAINT field_blocks_time_check CHECK (starts_at < ends_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_blocks');
        Schema::dropIfExists('field_availabilities');
        Schema::dropIfExists('field_division');
        Schema::dropIfExists('playing_fields');
        Schema::dropIfExists('venues');
    }
};
