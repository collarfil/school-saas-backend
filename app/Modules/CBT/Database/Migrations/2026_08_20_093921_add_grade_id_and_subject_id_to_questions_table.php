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
        Schema::table('questions', function (Blueprint $table) {
            // Add grade_id and subject_id columns after exam_id
            $table->foreignId('grade_id')
                  ->nullable() // nullable initially to safely update existing rows
                  ->after('exam_id')
                  ->constrained('grades')
                  ->cascadeOnDelete();

            $table->foreignId('subject_id')
                  ->nullable() // nullable initially to safely update existing rows
                  ->after('grade_id')
                  ->constrained('subjects')
                  ->cascadeOnDelete();

            // Index for faster queries when filtering by Grade + Subject
            $table->index(['grade_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Drop indexes and foreign keys cleanly if rolled back
            $table->dropIndex(['grade_id', 'subject_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['subject_id']);
            $table->dropColumn(['grade_id', 'subject_id']);
        });
    }
};