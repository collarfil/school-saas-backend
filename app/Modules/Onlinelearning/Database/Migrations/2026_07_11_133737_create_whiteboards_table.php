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
        Schema::create('whiteboards', function (Blueprint $table) {
           $table->id();

            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('page_number')->default(1);

            $table->longText('board_data');

            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whiteboards');
    }
};
