<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->foreignId('school_session_id')
                  ->nullable()
                  ->after('school_id')
                  ->constrained('school_sessions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropForeign(['school_session_id']);
            $table->dropColumn('school_session_id');
        });
    }
};