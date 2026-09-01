<?php
// database/migrations/2026_08_31_150435_add_subscription_fields_to_schools_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add subscription columns to schools table
        Schema::table('schools', function (Blueprint $table) {
            // Check if column exists before adding to avoid errors
            if (!Schema::hasColumn('schools', 'is_unlocked')) {
                $table->boolean('is_unlocked')->default(false);
            }
            
            if (!Schema::hasColumn('schools', 'has_free_subscription')) {
                $table->boolean('has_free_subscription')->default(false);
            }
            
            if (!Schema::hasColumn('schools', 'subscription_type')) {
                $table->string('subscription_type')->nullable();
            }
            
            if (!Schema::hasColumn('schools', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable();
            }
            
            if (!Schema::hasColumn('schools', 'subscription_id')) {
                $table->foreignId('subscription_id')->nullable();
            }
        });

        // Add foreign key constraint separately (if column exists)
        if (Schema::hasColumn('schools', 'subscription_id')) {
            // Check if foreign key doesn't already exist
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'schools' 
                AND CONSTRAINT_NAME != 'PRIMARY'
            ");
            
            $hasForeignKey = false;
            foreach ($foreignKeys as $fk) {
                if (strpos($fk->CONSTRAINT_NAME, 'schools_subscription_id_foreign') !== false) {
                    $hasForeignKey = true;
                    break;
                }
            }
            
            if (!$hasForeignKey) {
                Schema::table('schools', function (Blueprint $table) {
                    $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
                });
            }
        }

        // Handle existing schools - set them to FREE by default
        // This ensures existing schools don't get locked out
        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'has_free_subscription')) {
            DB::table('schools')->update([
                'is_unlocked' => true,
                'has_free_subscription' => true,
                'subscription_type' => 'free'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('schools', 'subscription_id')) {
                try {
                    $table->dropForeign(['subscription_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
            }
            
            // Drop columns
            $columns = ['is_unlocked', 'has_free_subscription', 'subscription_type', 'subscription_expires_at', 'subscription_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('schools', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};