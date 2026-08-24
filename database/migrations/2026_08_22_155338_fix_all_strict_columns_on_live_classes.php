<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get all columns on live_classes that are NOT NULL and have NO DEFAULT value
        $columns = DB::select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'live_classes'
              AND IS_NULLABLE = 'NO'
              AND COLUMN_DEFAULT IS NULL
        ");

        // System/required keys to ignore
        $ignore = ['id', 'school_id', 'grade_id', 'employee_id', 'subject_id', 'school_session_id', 'title', 'start_time', 'end_time', 'created_at', 'updated_at'];

        Schema::table('live_classes', function (Blueprint $table) use ($columns, $ignore) {
            foreach ($columns as $col) {
                $name = $col->COLUMN_NAME;
                $type = strtolower($col->DATA_TYPE);

                if (in_array($name, $ignore)) {
                    continue;
                }

                // Apply defaults based on column type
                if (in_array($type, ['tinyint', 'boolean'])) {
                    $table->boolean($name)->default(false)->change();
                } elseif (in_array($type, ['int', 'bigint', 'smallint'])) {
                    $table->integer($name)->default(0)->change();
                } elseif (in_array($type, ['varchar', 'text', 'string'])) {
                    $table->string($name)->nullable()->change();
                } elseif (in_array($type, ['date', 'datetime', 'timestamp'])) {
                    $table->dateTime($name)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        //
    }
};