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
        Schema::create('school_sessions', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // 2024/2025
    $table->string('term'); // 2024/2025
    $table->boolean('is_current')->default(false);
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
        Schema::dropIfExists('school_sessions');
    }
};
