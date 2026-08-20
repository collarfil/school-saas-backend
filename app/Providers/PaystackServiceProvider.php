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
        // 1. Update exam_types table
        Schema::table('exam_types', function (Blueprint $table) {
            $table->foreignId('school_session_id')
                  ->after('school_id')
                  ->constrained('school_sessions')
                  ->onDelete('cascade');

            // Drop old unique constraint if it exists, and add the session-scoped one
            // $table->dropUnique(['school_id', 'slug']); 
            $table->unique(['school_id', 'school_session_id', 'slug'], 'exam_types_session_slug_unique');
        });

        // 2. Update exams table
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('school_session_id')
                  ->after('school_id')
                  ->constrained('school_sessions')
                  ->onDelete('cascade');

            // Add an optimized index for quick CBT dashboard lookups
            $table->index(['school_id', 'school_session_id', 'status'], 'exams_session_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropCompositeIndex('exams_session_status_idx');
            $table->dropForeign(['school_session_id']);
            $table->dropColumn('school_session_id');
        });

        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropUnique('exam_types_session_slug_unique');
            $table->dropForeign(['school_session_id']);
            $table->dropColumn('school_session_id');
        });
    }
};