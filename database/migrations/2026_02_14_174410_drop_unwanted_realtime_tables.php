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
        // Drop in correct order (important if foreign keys exist)

        if (Schema::hasTable('class_messages')) {
            Schema::drop('class_messages');
        }

        if (Schema::hasTable('messages')) {
            Schema::drop('messages');
        }

        if (Schema::hasTable('class_sessions')) {
            Schema::drop('class_sessions');
        }

        if (Schema::hasTable('whatsapp_messages')) {
            Schema::drop('whatsapp_messages');
        }
    }

    public function down(): void
    {
        // Do nothing
    }
};
