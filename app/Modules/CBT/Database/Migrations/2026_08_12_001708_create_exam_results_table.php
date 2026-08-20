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
       Schema::create('exam_results', function (Blueprint $table) {
        $table->id();
        $table->foreignId('school_id')->constrained()->cascadeOnDelete();
        $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
        $table->foreignId('student_id')->constrained()->cascadeOnDelete();
        $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
        
        $table->decimal('score_obtained', 8, 2);
        $table->decimal('percentage', 5, 2);
        $table->boolean('is_passed');
        
        $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
        $table->text('teacher_remarks')->nullable();
        $table->timestamps();
        
        $table->unique(['exam_session_id']);
        $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
