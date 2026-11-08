<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Potrebno za izključitveno omejitev z enakostjo na shared_space_id.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_space_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_tenant_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // Cena, izračunana ob rezervaciji (kasnejša sprememba cenika je ne spremeni).
            $table->unsignedInteger('fee_cents');
            $table->string('status')->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['shared_space_id', 'starts_at']);
        });

        // Brez prekrivanja nepreklicanih rezervacij istega prostora, tudi pri sočasnih zahtevah (US-08).
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (shared_space_id WITH =, tsrange(starts_at, ends_at, '[)') WITH &&)
            WHERE (status <> 'cancelled')
        SQL);

        Schema::table('charges', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });
        Schema::dropIfExists('bookings');
    }
};
