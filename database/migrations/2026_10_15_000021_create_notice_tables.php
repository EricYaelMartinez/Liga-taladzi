<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->string('category', 30)->default('general')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->string('audience_type', 20);
            $table->json('audience_role_slugs')->nullable();
            $table->json('audience_team_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'status', 'published_at']);
        });

        Schema::create('league_notice_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('delivered_at');
            $table->timestampTz('read_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['league_notice_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('general_enabled')->default(true);
            $table->boolean('schedule_enabled')->default(true);
            $table->boolean('finance_enabled')->default(true);
            $table->boolean('discipline_enabled')->default(true);
            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE league_notices ADD CONSTRAINT league_notices_category_check CHECK (category IN ('general', 'schedule', 'finance', 'discipline'))");
            DB::statement("ALTER TABLE league_notices ADD CONSTRAINT league_notices_priority_check CHECK (priority IN ('normal', 'important', 'urgent'))");
            DB::statement("ALTER TABLE league_notices ADD CONSTRAINT league_notices_status_check CHECK (status IN ('draft', 'published', 'withdrawn'))");
            DB::statement("ALTER TABLE league_notices ADD CONSTRAINT league_notices_audience_check CHECK (audience_type IN ('all', 'roles', 'teams'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('league_notice_recipients');
        Schema::dropIfExists('league_notices');
    }
};
