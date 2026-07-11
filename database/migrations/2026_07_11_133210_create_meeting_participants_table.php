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
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->dateTime('joined_at');
            $table->dateTime('left_at');
            $table->unsignedBigInteger('attendance_duration')->default(0);
            $table->boolean('camera_enabled')->default(false);
            $table->boolean('microphone_enabled')->default(false);
            $table->boolean('hand_raised')->default(false);
            $table->string('connection_quality');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
