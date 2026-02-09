<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add composite unique index (school_id + admission_number)
            $table->unique(['school_id', 'admission_number'], 'students_school_admission_unique');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop the composite unique index
            $table->dropUnique('students_school_admission_unique');
        });
    }
};
