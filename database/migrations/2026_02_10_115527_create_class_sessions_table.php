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
            // database/migrations/xxxx_create_class_sessions_table.php
            Schema::create('class_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->foreignId('teacher_id')->constrained('users');

            $table->foreignId('grade_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('meeting_link');

            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();

            $table->boolean('is_live')->default(false);

            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
