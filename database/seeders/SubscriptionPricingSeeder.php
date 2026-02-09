<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPricing;

class SubscriptionPricingSeeder extends Seeder
{
    public function run()
    {
        // Deactivate all existing pricing
        SubscriptionPricing::query()->update(['is_active' => false]);

        // Create termly pricing
        SubscriptionPricing::create([
            'plan_type' => 'termly',
            'base_price' => 20000, // ₦200.00 in kobo
            'per_student_price' => 2000, // ₦20.00 per student
            'duration_days' => 120, // 4 months
            'description' => 'Per term subscription (4 months)',
            'is_active' => true
        ]);

        // Create yearly pricing
        SubscriptionPricing::create([
            'plan_type' => 'yearly',
            'base_price' => 50000, // ₦500.00 in kobo
            'per_student_price' => 5000, // ₦50.00 per student
            'duration_days' => 365, // 1 year
            'description' => 'Annual subscription (1 year)',
            'is_active' => true
        ]);
    }
}