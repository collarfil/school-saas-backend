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
        Schema::table('users', function (Blueprint $table) {
        $table->foreignId('school_id')->nullable()->constrained()->onDelete('cascade');
        $table->string('role')->default('admin'); // super_admin, admin, employee, student, parent
        $table->string('phone')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_login_at')->nullable();
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
