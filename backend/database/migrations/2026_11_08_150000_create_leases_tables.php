<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('notice_period_days');
            $table->unsignedInteger('monthly_rent_cents');
            $table->unsignedInteger('deposit_cents');
            $table->string('status')->default('draft');
            $table->date('notice_given_on')->nullable();
            $table->date('terminates_on')->nullable();
            $table->unsignedInteger('deposit_returned_cents')->nullable();
            $table->timestamps();

            $table->index(['apartment_id', 'status']);
        });

        // En aktiven najem na stanovanje, tudi pri sočasnih zahtevah.
        DB::statement(
            "CREATE UNIQUE INDEX leases_one_active_per_apartment ON leases (apartment_id) WHERE status IN ('active', 'in_notice')"
        );

        Schema::create('lease_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('rent_share_cents');
            $table->date('moved_in_on');
            $table->date('moved_out_on')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_tenants');
        Schema::dropIfExists('leases');
    }
};
