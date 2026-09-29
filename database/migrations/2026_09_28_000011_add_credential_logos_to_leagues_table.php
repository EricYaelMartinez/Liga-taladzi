<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table): void {
            $table->string('credential_logo_1_path')->nullable()->after('logo_path');
            $table->string('credential_logo_2_path')->nullable()->after('credential_logo_1_path');
            $table->string('credential_logo_3_path')->nullable()->after('credential_logo_2_path');
            $table->string('credential_logo_4_path')->nullable()->after('credential_logo_3_path');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table): void {
            $table->dropColumn([
                'credential_logo_1_path', 'credential_logo_2_path',
                'credential_logo_3_path', 'credential_logo_4_path',
            ]);
        });
    }
};
