<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('provider'); // 'paystack', 'stripe', 'flutterwave'
            $table->text('api_public_key');  // Encrypted in model
            $table->text('api_secret_key');  // Encrypted in model
            $table->text('webhook_secret')->nullable(); // Encrypted in model

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Enforce single active setup per provider per school tenant
            $table->unique(['school_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_gateways');
    }
};