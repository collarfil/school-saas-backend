<?php
// database/seeders/SchoolSubscriptionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\Subscription;
use Illuminate\Support\Str;

class SchoolSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();

        foreach ($schools as $school) {
            // Check if school already has a subscription
            $existingSubscription = Subscription::where('school_id', $school->id)->first();

            if (!$existingSubscription) {
                // Create free subscription for existing schools
                $subscription = Subscription::create([
                    'school_id' => $school->id,
                    'plan_type' => 'free',
                    'term' => $this->getCurrentTerm(),
                    'school_session' => date('Y') . '/' . (date('Y') + 1),
                    'student_capacity' => 1000,
                    'amount' => 0,
                    'payment_reference' => 'FREE-' . strtoupper(Str::random(10)),
                    'payment_status' => 'paid',
                    'payment_gateway' => 'system',
                    'payment_date' => now(),
                    'valid_from' => now(),
                    'valid_until' => now()->addYears(10), // 10 years free for existing schools
                    'status' => 'active',
                ]);

                // Update school with subscription
                $school->update([
                    'is_unlocked' => true,
                    'has_free_subscription' => true,
                    'subscription_type' => 'free',
                    'subscription_id' => $subscription->id,
                ]);

                $this->command->info("✅ Free subscription created for school: {$school->name}");
            } else {
                // Ensure school has correct subscription fields
                $school->update([
                    'is_unlocked' => true,
                    'has_free_subscription' => true,
                    'subscription_type' => 'free',
                    'subscription_id' => $existingSubscription->id,
                ]);

                $this->command->info("ℹ️ School already has subscription: {$school->name}");
            }
        }

        $this->command->info('✅ School subscriptions migration completed!');
    }

    private function getCurrentTerm(): string
    {
        $month = now()->month;
        
        if ($month >= 1 && $month <= 4) {
            return 'First Term';
        } elseif ($month >= 5 && $month <= 8) {
            return 'Second Term';
        } else {
            return 'Third Term';
        }
    }
}