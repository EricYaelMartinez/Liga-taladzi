<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger', 20)->default('scheduled');
            $table->string('status', 20)->default('running')->index();
            $table->string('filename')->nullable();
            $table->string('path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['trigger', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
