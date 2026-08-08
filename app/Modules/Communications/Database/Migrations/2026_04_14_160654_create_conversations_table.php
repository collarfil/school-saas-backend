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
    Schema::create('conversations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('school_id')->constrained()->cascadeOnDelete();
        $table->string('type'); 
        $table->string('name')->nullable();

        // Standard links that work
        $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();

        // THE FIX: Define the column without the foreign key for now
        $table->unsignedBigInteger('class_session_id')->nullable();

        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
