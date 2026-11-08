<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Povezave obveznosti na izvor (US-04, #105). Tuji ključi se dodajo skupaj
 * s tabelami položnic, rezervacij in prijav okvar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedBigInteger('bill_share_id')->nullable()->after('paid_at');
            $table->unsignedBigInteger('booking_id')->nullable()->after('bill_share_id');
            $table->unsignedBigInteger('repair_request_id')->nullable()->after('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['bill_share_id', 'booking_id', 'repair_request_id']);
        });
    }
};
