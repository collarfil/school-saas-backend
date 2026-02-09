<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_pricings', function (Blueprint $table) {
            $table->id();
            $table->string('plan_type'); // termly, yearly
            $table->decimal('base_price', 10, 2);
            $table->decimal('per_student_price', 10, 2);
            $table->integer('duration_days');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default pricing
        DB::table('subscription_pricings')->insert([
            [
                'plan_type' => 'termly',
                'base_price' => 20000,
                'per_student_price' => 2000,
                'duration_days' => 120, // 4 months
                'description' => 'Per term subscription',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'plan_type' => 'yearly',
                'base_price' => 50000,
                'per_student_price' => 5000,
                'duration_days' => 365, // 1 year
                'description' => 'Annual subscription (3 terms)',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_pricings');
    }
};