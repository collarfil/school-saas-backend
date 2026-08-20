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
        Schema::create('questions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
    $table->text('question_text');
    $table->string('question_image')->nullable(); // For structural diagrams/formula charts
    $table->string('type')->default('multiple_choice'); // multiple_choice, true_false
    $table->decimal('marks', 5, 2)->default(1.00); // Dynamic question weights
    $table->text('explanation')->nullable(); // Shown during post-exam review flags
    $table->timestamps();
    $table->softDeletes();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
