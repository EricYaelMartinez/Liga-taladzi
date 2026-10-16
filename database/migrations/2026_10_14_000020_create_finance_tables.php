<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_participations', function (Blueprint $table): void {
            $table->string('financial_status', 20)->default('clear')->after('status')->index();
            $table->string('suspension_source', 20)->nullable()->after('financial_status');
            $table->timestampTz('financial_status_changed_at')->nullable()->after('suspension_source');
            $table->timestampTz('financial_suspended_at')->nullable()->after('financial_status_changed_at');
            $table->timestampTz('financial_excluded_at')->nullable()->after('financial_suspended_at');
        });

        Schema::create('finance_concepts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('fee');
            $table->decimal('default_amount', 12, 2);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['league_id', 'name']);
        });

        Schema::create('finance_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_participation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('concept_id')->nullable()->constrained('finance_concepts')->restrictOnDelete();
            $table->string('source_key', 150)->nullable();
            $table->string('description', 250);
            $table->string('type', 20)->default('fee');
            $table->decimal('original_amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('grace_until')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['league_id', 'source_key']);
            $table->index(['league_id', 'team_id', 'status']);
            $table->index(['team_participation_id', 'due_on']);
        });

        Schema::create('finance_receipt_sequences', function (Blueprint $table): void {
            $table->foreignId('league_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number', 40);
            $table->decimal('amount', 12, 2);
            $table->string('method', 20)->default('cash');
            $table->timestampTz('paid_at');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['league_id', 'receipt_number']);
            $table->index(['league_id', 'team_id', 'paid_at']);
        });

        Schema::create('finance_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('finance_payments')->restrictOnDelete();
            $table->foreignId('charge_id')->constrained('finance_charges')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'charge_id']);
        });

        DB::table('leagues')->orderBy('id')->get()->each(function (object $league): void {
            DB::table('finance_receipt_sequences')->insert([
                'league_id' => $league->id,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE team_participations ADD CONSTRAINT team_participations_financial_status_check CHECK (financial_status IN ('clear', 'grace', 'suspended', 'excluded'))");
            DB::statement("ALTER TABLE team_participations ADD CONSTRAINT team_participations_suspension_source_check CHECK (suspension_source IS NULL OR suspension_source IN ('administrative', 'financial'))");
            DB::statement("ALTER TABLE finance_concepts ADD CONSTRAINT finance_concepts_type_check CHECK (type IN ('fee', 'bond', 'fine', 'other'))");
            DB::statement("ALTER TABLE finance_charges ADD CONSTRAINT finance_charges_type_check CHECK (type IN ('fee', 'bond', 'fine', 'other'))");
            DB::statement("ALTER TABLE finance_charges ADD CONSTRAINT finance_charges_status_check CHECK (status IN ('pending', 'partial', 'paid', 'cancelled'))");
            DB::statement("ALTER TABLE finance_payments ADD CONSTRAINT finance_payments_method_check CHECK (method IN ('cash'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_allocations');
        Schema::dropIfExists('finance_payments');
        Schema::dropIfExists('finance_receipt_sequences');
        Schema::dropIfExists('finance_charges');
        Schema::dropIfExists('finance_concepts');
        Schema::table('team_participations', function (Blueprint $table): void {
            $table->dropColumn(['financial_status', 'suspension_source', 'financial_status_changed_at', 'financial_suspended_at', 'financial_excluded_at']);
        });
    }
};
