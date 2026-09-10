<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            /*
            |--------------------------------------------------------------------------
            | TENANT
            |--------------------------------------------------------------------------
            */

            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | USERS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | TICKET LOOKUP TABLES
            |--------------------------------------------------------------------------
            */

            $table->foreignId('ticket_category_id')
                ->constrained('ticket_categories')
                ->restrictOnDelete();

            $table->foreignId('ticket_priority_id')
                ->constrained('ticket_priorities')
                ->restrictOnDelete();

            $table->foreignId('ticket_status_id')
                ->constrained('ticket_statuses')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | TICKET INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->string('ticket_number')
                ->unique();

            $table->string('subject');

            $table->longText('description');

            /*
            |--------------------------------------------------------------------------
            | RESOLUTION
            |--------------------------------------------------------------------------
            */

            $table->text('resolution')
                ->nullable();

            $table->timestamp('resolved_at')
                ->nullable();

            $table->timestamp('closed_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index([
                'school_id',
                'ticket_status_id'
            ]);

            $table->index([
                'ticket_category_id',
                'ticket_priority_id'
            ]);

            $table->index([
                'assigned_to',
                'ticket_status_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};