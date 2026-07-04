<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('grades')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->string('meeting_id')->unique();
            $table->string('meeting_password')->nullable();
            $table->enum('status', ['scheduled', 'active', 'ended'])->default('scheduled');
            $table->string('recording_url')->nullable();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('video_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['video_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_participants');
        Schema::dropIfExists('video_sessions');
    }
};