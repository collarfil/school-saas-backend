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
        Schema::table('live_classes', function (Blueprint $table) {
            // Set defaults for existing columns causing SQL 1364 errors
            if (Schema::hasColumn('live_classes', 'is_recorded')) {
                $table->boolean('is_recorded')->default(false)->change();
            }
            if (Schema::hasColumn('live_classes', 'meeting_code')) {
                $table->string('meeting_code')->nullable()->change();
            }
            if (Schema::hasColumn('live_classes', 'schedule_date')) {
                $table->date('schedule_date')->nullable()->change();
            }
            if (Schema::hasColumn('live_classes', 'meeting_password')) {
                $table->string('meeting_password')->nullable()->change();
            }
            if (Schema::hasColumn('live_classes', 'recurrence_pattern')) {
                $table->string('recurrence_pattern')->nullable()->change();
            }
            if (Schema::hasColumn('live_classes', 'max_participants')) {
                $table->integer('max_participants')->default(0)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};