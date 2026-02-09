<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('school_sessions', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('school_sessions', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('is_current');
                
                // Add foreign key constraint
                $table->foreign('school_id')
                    ->references('id')
                    ->on('schools')
                    ->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('school_sessions', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['school_id']);
            // Then drop the column
            $table->dropColumn('school_id');
        });
    }
};