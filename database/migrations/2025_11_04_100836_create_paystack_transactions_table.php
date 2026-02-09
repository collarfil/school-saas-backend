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
        
        Schema::create('paystack_transactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('school_id')->constrained()->onDelete('cascade');
        $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('set null');
        $table->string('reference')->unique();
        $table->decimal('amount', 10, 2);
        $table->string('currency')->default('NGN');
        $table->string('channel')->nullable(); // card, bank, etc
        $table->string('status'); // success, failed, abandoned
        $table->string('gateway_response');
        $table->string('customer_email');
        $table->string('customer_id')->nullable();
        $table->json('metadata')->nullable();
        $table->json('gateway_data')->nullable(); // Full paystack response
        $table->timestamps();
    });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paystack_transactions');
    }
};
