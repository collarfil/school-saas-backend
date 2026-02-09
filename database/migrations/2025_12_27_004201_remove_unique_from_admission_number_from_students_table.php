<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get the index name for the unique constraint
        $table = 'students';
        $indexName = null;

        // Query the information_schema for the unique key name
        $uniqueKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND CONSTRAINT_TYPE = 'UNIQUE'
        ", [$table]);

        foreach ($uniqueKeys as $key) {
            // If the unique constraint is on admission_number, get its name
            $columns = DB::select("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
            ", [$table, $key->CONSTRAINT_NAME]);

            if (count($columns) === 1 && $columns[0]->COLUMN_NAME === 'admission_number') {
                $indexName = $key->CONSTRAINT_NAME;
                break;
            }
        }

        if ($indexName) {
            Schema::table('students', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unique('admission_number');
        });
    }
};
