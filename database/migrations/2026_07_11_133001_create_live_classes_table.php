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
        Schema::create('live_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreignId('grade_id');
            $table->foreignId('employee_id');
            $table->foreignId('subject_id');
            $table->foreignId('school_session_id');
            $table->string ('title');
            $table->string('description');
            $table->string('meeting_provider');
            $table->text('meeting_url');
            $table->string('meeting_code');
            $table->date('schedule_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status');
            $table->boolean('is_recorded');
            $table->boolean('allow_chat');
            $table->boolean('allow_screen_share');
            $table->boolean('allow_student_microphone');
            $table->boolean('allow_student_camera');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_classes');
    }
};
