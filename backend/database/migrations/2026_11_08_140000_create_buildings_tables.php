<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->unsignedSmallInteger('rent_due_day')->default(8);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['user_id', 'building_id', 'role']);
        });

        Schema::create('apartments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->smallInteger('floor');
            $table->decimal('area_m2', 6, 2);
            $table->unsignedSmallInteger('rooms');
            $table->timestamps();

            $table->unique(['building_id', 'label']);
        });

        Schema::create('shared_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_per_hour_cents')->default(0);
            $table->time('open_from');
            $table->time('open_to');
            $table->unsignedSmallInteger('max_hours_per_week');
            $table->unsignedSmallInteger('free_cancel_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('repair_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('default_payer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_categories');
        Schema::dropIfExists('shared_spaces');
        Schema::dropIfExists('apartments');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('buildings');
    }
};
