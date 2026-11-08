<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            // Prazno, če položnica velja za celo stavbo.
            $table->foreignId('apartment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->unsignedInteger('amount_cents');
            $table->date('period_start');
            $table->date('period_end');
            // Delitev med stanovanja (samo pri položnici za celo stavbo).
            $table->string('split_method')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('bill_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('apartment_id')->constrained()->restrictOnDelete();
            // Prazno pri deležu praznega stanovanja, ki ostane lastniku.
            $table->foreignId('lease_tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('days');
            $table->unsignedInteger('amount_cents');
            $table->timestamps();
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->foreign('bill_share_id')->references('id')->on('bill_shares')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropForeign(['bill_share_id']);
        });
        Schema::dropIfExists('bill_shares');
        Schema::dropIfExists('utility_bills');
    }
};
