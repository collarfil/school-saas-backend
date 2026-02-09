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
    Schema::create('fees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('grade_id')->nullable()->constrained('grades')->onDelete('cascade');
    $table->foreignId('school_session_id')->constrained()->onDelete('cascade');
    $table->string('term');
    $table->decimal('amount', 10, 2);
    $table->string('description')->nullable();
    $table->timestamps();
    $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fees');
    }
};
