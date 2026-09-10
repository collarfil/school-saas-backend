<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Add UUID column
            $table->uuid('uuid')->after('id')->nullable()->unique();

            // Drop subdomain column if it exists
            if (Schema::hasColumn('schools', 'subdomain')) {
                $table->dropColumn('subdomain');
            }
        });

        // Generate UUIDs for any existing school records
        $schools = DB::table('schools')->whereNull('uuid')->get();
        foreach ($schools as $school) {
            DB::table('schools')
                ->where('id', $school->id)
                ->update(['uuid' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('uuid');
            $table->string('subdomain')->nullable()->unique();
        });
    }
};