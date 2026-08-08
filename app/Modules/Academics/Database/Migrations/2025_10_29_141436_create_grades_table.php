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
    Schema::create('grades', function (Blueprint $table) {
    $table->id();
    $table->foreignId('section_id')->constrained()->onDelete('cascade');
    $table->string('name'); // e.g. Primary 1$table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
