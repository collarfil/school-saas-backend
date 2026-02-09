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
        Schema::table('fee_payments', function (Blueprint $table) {
            // Add payment_reference column (nullable for existing records)
            $table->string('payment_reference')->nullable()->after('status');
            
            // Add payment_method column
            $table->string('payment_method')->nullable()->after('payment_reference');
            
            // Add index for faster queries
            $table->index('payment_reference');
            
            // Add index for payment_method if needed
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            // Drop the columns
            $table->dropColumn(['payment_reference', 'payment_method']);
        });
    }
};