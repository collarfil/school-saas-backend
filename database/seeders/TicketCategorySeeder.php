<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Technical Issue',
                'slug' => 'technical-issue',
                'description' => 'System errors, bugs, performance and technical problems.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Account & Login',
                'slug' => 'account-login',
                'description' => 'Login, password, account access and authentication issues.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Student Management',
                'slug' => 'student-management',
                'description' => 'Issues relating to student records and management.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Parent Management',
                'slug' => 'parent-management',
                'description' => 'Issues relating to parent records and accounts.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Employee Management',
                'slug' => 'employee-management',
                'description' => 'Issues relating to staff and employee management.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Attendance',
                'slug' => 'attendance',
                'description' => 'Attendance and attendance reporting issues.',
                'sort_order' => 6,
            ],
            [
                'name' => 'Results & Examinations',
                'slug' => 'results-examinations',
                'description' => 'Results, grading, examinations and report-related issues.',
                'sort_order' => 7,
            ],
            [
                'name' => 'Fees & Payments',
                'slug' => 'fees-payments',
                'description' => 'School fees, payment and transaction issues.',
                'sort_order' => 8,
            ],
            [
                'name' => 'Subscription & Billing',
                'slug' => 'subscription-billing',
                'description' => 'Platform subscription and billing issues.',
                'sort_order' => 9,
            ],
            [
                'name' => 'Feature Request',
                'slug' => 'feature-request',
                'description' => 'Requests for new platform features or improvements.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'description' => 'Issues that do not fall into another category.',
                'sort_order' => 11,
            ],
        ];

        foreach ($categories as $category) {
            TicketCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }
    }
}