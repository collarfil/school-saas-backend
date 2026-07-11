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
    Schema::create('assignments', function (Blueprint $table) { // <--- Made plural
        $table->id();
        $table->foreignId('school_id')->constrained()->cascadeOnDelete();
        $table->foreignId('live_class_id')->constrained()->cascadeOnDelete();
        $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
        $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
        $table->string('title');
        $table->text('instruction');
        $table->text('attachment')->nullable();
        $table->dateTime('available_from');
        $table->dateTime('due_date');
        $table->decimal('max_score', 8, 2);
        $table->boolean('allow_late_submission')->default(false);
        $table->string('status')->default('draft');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('assignments'); // <--- Made plural
}
};