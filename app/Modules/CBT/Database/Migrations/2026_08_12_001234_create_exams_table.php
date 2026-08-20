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
        Schema::create('exams', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    // Examiner who created/manages it (Scoped to teaching employees)
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete(); 
    
    $table->string('title');
    $table->text('instruction')->nullable();
    $table->string('attachment')->nullable(); // Path to PDF/Images if applicable
    
    $table->dateTime('available_from');
    $table->dateTime('due_date');
    $table->unsignedInteger('duration_minutes'); // Tracked as raw unsigned integer
    
    $table->decimal('max_score', 8, 2)->default(100.00);
    $table->decimal('pass_mark', 8, 2)->default(40.00);
    
    // CBT Settings configurations
    $table->boolean('randomize_questions')->default(false);
    $table->boolean('randomize_options')->default(false);
    $table->boolean('show_result_immediately')->default(false);
    $table->boolean('allow_late_submission')->default(false);
    
    $table->string('status')->default('draft'); // draft, published, closed
    $table->timestamps();
    $table->softDeletes();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
