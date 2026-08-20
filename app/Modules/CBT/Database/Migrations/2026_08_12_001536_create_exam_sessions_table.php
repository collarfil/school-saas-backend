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
        Schema::create('exam_sessions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('school_id')->constrained()->cascadeOnDelete();
        $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
        $table->foreignId('student_id')->constrained()->cascadeOnDelete();
        
        $table->dateTime('started_at');
        $table->dateTime('submitted_at')->nullable();
        $table->dateTime('expires_at'); // calculated automatically upon starting (started_at + duration_minutes)
        
        // Operational states to defend against internet disconnects/power failures
        $table->string('status')->default('active'); // active, suspended, submitted, timed_out
        $table->string('ip_address', 45)->nullable();
        $table->string('user_agent')->nullable();
        
        $table->timestamps();
        
    });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
