<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('match_periods')->default(2);
            $table->unsignedSmallInteger('period_duration_minutes')->nullable();
            $table->unsignedSmallInteger('halftime_minutes')->nullable();
            $table->unsignedSmallInteger('schedule_buffer_minutes')->nullable();
            $table->unsignedSmallInteger('appeal_deadline_hours')->default(2);
            $table->unsignedSmallInteger('payment_grace_days')->nullable();
            $table->unsignedSmallInteger('reactivation_window_days')->default(21);
            $table->boolean('bond_enabled')->default(false);
            $table->decimal('bond_amount', 12, 2)->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_settings');
    }
};
