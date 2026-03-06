<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update conversations
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'type')) {
                $table->string('type')->default('private')->after('id');
            }

            if (!Schema::hasColumn('conversations', 'name')) {
                $table->string('name')->nullable()->after('type');
            }

            if (!Schema::hasColumn('conversations', 'class_id')) {
                $table->foreignId('class_id')->nullable()->constrained()->nullOnDelete();
            }

            $table->index('type');
        });

        // Update conversation_participants
        Schema::table('conversation_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('conversation_participants', 'role')) {
                $table->string('role')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('conversation_participants', 'last_read_at')) {
                $table->timestamp('last_read_at')->nullable()->after('role');
            }

            $table->index(['conversation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn(['role', 'last_read_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['type', 'name', 'class_id']);
        });
    }
};
