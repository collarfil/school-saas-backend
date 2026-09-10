<?php

namespace Database\Seeders;

use App\Models\TicketStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicketStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Open',
                'slug' => 'open',
                'description' => 'New ticket awaiting support attention.',
                'is_initial' => true,
                'is_final' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'In Progress',
                'slug' => 'in-progress',
                'description' => 'Ticket is currently being handled.',
                'is_initial' => false,
                'is_final' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Waiting for School',
                'slug' => 'waiting-for-school',
                'description' => 'Support is waiting for additional information from the school.',
                'is_initial' => false,
                'is_final' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Resolved',
                'slug' => 'resolved',
                'description' => 'The reported issue has been resolved.',
                'is_initial' => false,
                'is_final' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Closed',
                'slug' => 'closed',
                'description' => 'Ticket has been permanently closed.',
                'is_initial' => false,
                'is_final' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($statuses as $status) {
            TicketStatus::updateOrCreate(
                ['slug' => $status['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $status['name'],
                    'description' => $status['description'],
                    'is_initial' => $status['is_initial'],
                    'is_final' => $status['is_final'],
                    'is_active' => true,
                    'sort_order' => $status['sort_order'],
                ]
            );
        }
    }
}