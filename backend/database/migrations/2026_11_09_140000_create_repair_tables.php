<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('apartment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('shared_space_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('repair_categories')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('reported');
            // Privzeti plačnik iz vrste okvare se shrani, da je kasnejša sprememba sledljiva.
            $table->string('default_payer');
            $table->string('payer');
            $table->foreignId('contractor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            // Najemnik, ki plača strošek, ko je plačnik najemnik (ob zaključku).
            $table->foreignId('payer_lease_tenant_id')->nullable()->constrained('lease_tenants')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['building_id', 'status']);
        });

        Schema::create('repair_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            // Prazno pri sistemskem dogodku (npr. privzeti plačnik iz vrste okvare).
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind')->default('status');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            // report = fotografije prijave, completion = fotografije izvajalca
            $table->string('context')->default('report');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->foreign('repair_request_id')->references('id')->on('repair_requests')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropForeign(['repair_request_id']);
        });
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('repair_events');
        Schema::dropIfExists('repair_requests');
    }
};
