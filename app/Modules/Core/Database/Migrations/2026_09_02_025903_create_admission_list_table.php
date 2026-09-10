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
        Schema::create('admission_lists', function (Blueprint $table) {
    $table->id();
    
    // Multi-Tenancy & Academic Scope
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_session_id')->constrained('school_sessions')->cascadeOnDelete();
    $table->string('term')->nullable(); // e.g., First Term
    $table->foreignId('grade_id')->nullable()->constrained('grades')->cascadeOnDelete(); // Target Class (nullable if list covers whole school)

    // List Metadata
    $table->string('title'); // e.g., "2026/2027 First Batch Merit List"
    $table->string('batch_number')->default('1st Batch'); // e.g., 1st Batch, 2nd Batch, Supplementary
    
    // Publishing Status
    $table->boolean('is_published')->default(false)->index();
    $table->timestamp('published_at')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_list');
    }
};
