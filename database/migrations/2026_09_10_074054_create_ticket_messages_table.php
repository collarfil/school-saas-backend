<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * school_admin
             * super_admin
             * support_staff
             */
            $table->string('sender_type');

            $table->longText('message');

            // false = normal public conversation
            // true = internal support note
            $table->boolean('is_internal')
                ->default(false);

            $table->timestamps();

            $table->index([
                'ticket_id',
                'created_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};