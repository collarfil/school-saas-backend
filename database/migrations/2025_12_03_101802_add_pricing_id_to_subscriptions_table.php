<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add pricing_id column
            $table->foreignId('pricing_id')
                  ->nullable()
                  ->constrained('subscription_pricings')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['pricing_id']);
            $table->dropColumn('pricing_id');
        });
    }
};
