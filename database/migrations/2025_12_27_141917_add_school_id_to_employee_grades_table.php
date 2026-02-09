<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employee_grades', function (Blueprint $table) {
            // Add school_id column
            $table->foreignId('school_id')->nullable()->after('id');
            
            // Add foreign key constraint
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            
            // Add unique constraint to prevent duplicate assignments within a school
            $table->unique(['school_id', 'employee_id', 'grade_id'], 'employee_grade_school_unique');
        });
    }

    public function down()
    {
        Schema::table('employee_grades', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropUnique('employee_grade_school_unique');
            $table->dropColumn('school_id');
        });
    }
};