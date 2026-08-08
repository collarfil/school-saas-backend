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
        Schema::create('assignment_submission', function (Blueprint $table) {
            $table->id();

            // Explicitly targeting the singular 'assignment' table here:
            $table->foreignId('assignment_id')->constrained('assignment')->cascadeOnDelete();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->longText('submission_text')->nullable();
            $table->text('attachment')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users');
            $table->dateTime('graded_at')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submission');
    }
};