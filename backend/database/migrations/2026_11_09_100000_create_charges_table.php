<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_tenant_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('description');
            // Negativen znesek je dobropis (US-05).
            $table->integer('amount_cents');
            $table->date('period');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['period', 'paid_at']);
        });

        // Največ ena obveznost za najemnino na najemnika in mesec (idempotentnost, US-03).
        DB::statement(
            "CREATE UNIQUE INDEX charges_one_rent_per_period ON charges (lease_tenant_id, period) WHERE type = 'rent'"
        );

        // Mesečni povzetek se vsakemu najemniku pošlje največ enkrat na mesec.
        Schema::create('monthly_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->timestamp('sent_at');

            $table->unique(['user_id', 'building_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_statements');
        Schema::dropIfExists('charges');
    }
};
