<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Array of tables to add school_id to
        $tables = [
            'attendances',
            'employees',
            'expenses',
            'incomes',
            'fees',
            'fee_payments',
            'transactions',
            'results',
            'parents',
            'students',
            'sections',
            'grades',
            'subjects'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Check if school_id column already exists
                    if (!Schema::hasColumn($tableName, 'school_id')) {
                        // Add school_id column
                        $table->foreignId('school_id')->nullable()->after('id');
                        
                        // Add foreign key constraint
                        $table->foreign('school_id')
                            ->references('id')
                            ->on('schools')
                            ->onDelete('cascade');
                            
                        echo "Added school_id to {$tableName}\n";
                    } else {
                        echo "school_id already exists in {$tableName}\n";
                    }
                });
            } else {
                echo "Table {$tableName} does not exist\n";
            }
        }
    }

    public function down()
    {
        $tables = [
            'attendances',
            'employees',
            'expenses',
            'incomes',
            'fees',
            'fee_payments',
            'transactions',
            'results',
            'parents',
            'students',
            'sections',
            'grades',
            'subjects'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'school_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Drop foreign key first
                    $table->dropForeign(['school_id']);
                    // Then drop the column
                    $table->dropColumn('school_id');
                    
                    echo "Removed school_id from {$tableName}\n";
                });
            }
        }
    }
};