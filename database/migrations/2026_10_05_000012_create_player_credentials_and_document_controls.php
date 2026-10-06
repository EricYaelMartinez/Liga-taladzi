<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_documents', function (Blueprint $table): void {
            $table->string('original_name')->nullable()->after('path');
            $table->string('mime_type', 100)->nullable()->after('original_name');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->foreignId('deleted_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->string('deletion_reason', 500)->nullable()->after('deleted_by');
            $table->softDeletes();
        });

        Schema::create('player_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_registration_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('folio', 40)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->json('snapshot');
            $table->timestampTz('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['league_id', 'sequence']);
            $table->index(['player_registration_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX player_credentials_one_active_registration ON player_credentials (player_registration_id) WHERE status = 'active'");
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE player_credentials ADD CONSTRAINT player_credentials_status_check CHECK (status IN ('active', 'revoked'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_credentials');
        Schema::table('player_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['original_name', 'mime_type', 'size_bytes', 'deletion_reason', 'deleted_at']);
        });
    }
};
