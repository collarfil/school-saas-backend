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
        Schema::table('admission', function (Blueprint $table) {
            // 1. Safe Foreign Key & Academic Period (Nullable to prevent constraint errors on existing rows)
            if (!Schema::hasColumn('admission', 'school_session_id')) {
                $table->foreignId('school_session_id')
                      ->after('school_id')
                      ->nullable()
                      ->constrained('school_sessions')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('admission', 'term')) {
                $table->string('term')->after('school_session_id')->nullable();
            }

            // 2. Application Identifier & Academic Background
            if (!Schema::hasColumn('admission', 'application_number')) {
                $table->string('application_number')->after('id')->nullable()->unique();
            }

            if (!Schema::hasColumn('admission', 'prev_school')) {
                $table->string('prev_school')->after('prev_grade')->nullable();
            }

            // 3. Name & Candidate Details Handling
            if (!Schema::hasColumn('admission', 'first_name')) {
                // If 'name' column exists, rename it safely; otherwise create 'first_name'
                if (Schema::hasColumn('admission', 'name')) {
                    $table->renameColumn('name', 'first_name');
                } else {
                    $table->string('first_name')->after('prev_school')->nullable();
                }
            }

            if (!Schema::hasColumn('admission', 'middle_name')) {
                $table->string('middle_name')->after('first_name')->nullable();
            }

            if (!Schema::hasColumn('admission', 'last_name')) {
                $table->string('last_name')->after('middle_name')->nullable();
            }

            if (!Schema::hasColumn('admission', 'date_of_birth')) {
                $table->date('date_of_birth')->after('gender')->nullable();
            }

            if (!Schema::hasColumn('admission', 'email')) {
                $table->string('email')->after('phone')->nullable();
            }

            // 4. Parent / Guardian Information
            if (!Schema::hasColumn('admission', 'guardian_name')) {
                $table->string('guardian_name')->nullable();
                $table->string('guardian_relationship')->nullable();
                $table->string('guardian_phone')->nullable();
                $table->string('guardian_email')->nullable();
            }

            // 5. Workflow, Status, and Interview Tracking
            if (!Schema::hasColumn('admission', 'status')) {
                $table->string('status')->default('pending')->index();
            }

            if (!Schema::hasColumn('admission', 'interview_date')) {
                $table->dateTime('interview_date')->nullable();
                $table->string('interview_venue')->nullable();
                $table->text('interview_notes')->nullable();
                $table->timestamp('interview_notified_at')->nullable();
            }

            // 6. Decision & List Association
            if (!Schema::hasColumn('admission', 'decision_notified_at')) {
                $table->timestamp('decision_notified_at')->nullable();
                $table->text('rejection_reason')->nullable();
            }

            if (!Schema::hasColumn('admission', 'admission_list_id')) {
                $table->foreignId('admission_list_id')
                      ->nullable()
                      ->constrained('admission_lists')
                      ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission', function (Blueprint $table) {
            // Drop Foreign Keys first
            if (Schema::hasColumn('admission', 'school_session_id')) {
                $table->dropForeign(['school_session_id']);
            }
            if (Schema::hasColumn('admission', 'admission_list_id')) {
                $table->dropForeign(['admission_list_id']);
            }

            // Drop added columns safely
            $columnsToDrop = [
                'school_session_id', 'term', 'application_number', 'prev_school',
                'middle_name', 'last_name', 'date_of_birth', 'email',
                'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email',
                'status', 'interview_date', 'interview_venue', 'interview_notes',
                'interview_notified_at', 'decision_notified_at', 'rejection_reason', 'admission_list_id'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('admission', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('admission', 'first_name')) {
                $table->renameColumn('first_name', 'name');
            }
        });
    }
};