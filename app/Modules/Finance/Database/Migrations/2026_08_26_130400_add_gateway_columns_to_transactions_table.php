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
        Schema::table('transactions', function (Blueprint $table) {
            // Track who made the payment (Parent or Student user account)
            $table->foreignId('paid_by_user_id')
                ->nullable()
                ->after('school_id')
                ->constrained('users')
                ->nullOnDelete();

            // Online Gateway Tracking
            $table->string('gateway_reference')->nullable()->index()->after('reference');
            $table->decimal('gateway_fee', 10, 2)->default(0.00)->after('amount');
            $table->string('currency', 3)->default('NGN')->after('gateway_fee');

            // Payload & Completion Audit
            $table->json('raw_response')->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('raw_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['paid_by_user_id']);
            $table->dropColumn([
                'paid_by_user_id',
                'gateway_reference',
                'gateway_fee',
                'currency',
                'raw_response',
                'paid_at',
            ]);
        });
    }
};