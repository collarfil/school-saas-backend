<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $flags = [
                'allow_chat',
                'is_recorded',
                'auto_recording',
                'allow_raise_hand',
                'mute_on_entry',
                'waiting_room',
                'recurring'
            ];

            foreach ($flags as $column) {
                if (Schema::hasColumn('live_classes', $column)) {
                    $table->boolean($column)->default(false)->change();
                }
            }

            // String and numeric defaults
            if (Schema::hasColumn('live_classes', 'max_participants')) {
                $table->integer('max_participants')->default(0)->change();
            }
            if (Schema::hasColumn('live_classes', 'status')) {
                $table->string('status')->default('scheduled')->change();
            }
        });
    }

    public function down(): void
    {
        //
    }
};