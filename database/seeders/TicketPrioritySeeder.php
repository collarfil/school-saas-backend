<?php

namespace Database\Seeders;

use App\Models\TicketPriority;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicketPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            [
                'name' => 'Low',
                'slug' => 'low',
                'description' => 'Minor issue that does not significantly affect operations.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Medium',
                'slug' => 'medium',
                'description' => 'Normal issue requiring attention.',
                'sort_order' => 2,
            ],
            [
                'name' => 'High',
                'slug' => 'high',
                'description' => 'Important issue affecting school operations.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Urgent',
                'slug' => 'urgent',
                'description' => 'Critical issue requiring immediate attention.',
                'sort_order' => 4,
            ],
        ];

        foreach ($priorities as $priority) {
            TicketPriority::updateOrCreate(
                ['slug' => $priority['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $priority['name'],
                    'description' => $priority['description'],
                    'is_active' => true,
                    'sort_order' => $priority['sort_order'],
                ]
            );
        }
    }
}