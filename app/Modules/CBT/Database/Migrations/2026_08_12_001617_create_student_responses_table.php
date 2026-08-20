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
        Schema::create('student_responses', function (Blueprint $table) {
        $table->id();
        $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
        $table->foreignId('question_id')->constrained()->cascadeOnDelete();
        
        // Store selected option ID for standard MCQs
        $table->foreignId('option_id')->nullable()->constrained()->cascadeOnDelete();
        
        // Fallback text entry column for open-ended or fill-in-the-blank expansions
        $table->text('text_answer')->nullable(); 
        
        $table->boolean('is_correct')->default(false); // Auto-populated via backend hook on save
        $table->timestamps();
        
        $table->unique(['exam_session_id', 'question_id']);
        
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_responses');
    }
};
