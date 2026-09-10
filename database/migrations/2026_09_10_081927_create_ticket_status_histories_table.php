<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            $table->foreignId('old_ticket_status_id')
                ->nullable()
                ->constrained('ticket_statuses')
                ->nullOnDelete();

            $table->foreignId('new_ticket_status_id')
                ->constrained('ticket_statuses')
                ->restrictOnDelete();

            $table->foreignId('changed_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('comment')
                ->nullable();

            $table->timestamps();

            $table->index([
                'ticket_id',
                'created_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_histories');
    }
};