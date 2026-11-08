<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidenca potrditve odpovedi za vsakega sostanovalca (US-02, #97).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_tenants', function (Blueprint $table) {
            $table->timestamp('notice_confirmed_at')->nullable()->after('moved_out_on');
        });
    }

    public function down(): void
    {
        Schema::table('lease_tenants', function (Blueprint $table) {
            $table->dropColumn('notice_confirmed_at');
        });
    }
};
