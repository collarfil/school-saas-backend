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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreignId('live_class_id');
            $table->string('provider');
            $table->string('meeting_id');
            $table->string('meeting_password');
            $table->text('meeting_url');
            $table->datetime('started_at');
            $table->dateTime('ended_at');
            $table->integer('duration');
            $table->unsignedInteger('total_participants');
            $table->string('status');
            $table->boolean('recording_available')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
