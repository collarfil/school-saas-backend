<?php

namespace App\Support;

class TicketWorkflow
{
    public const TRANSITIONS = [

        'open' => [
            'in-progress',
        ],

        'in-progress' => [
            'waiting-for-school',
            'resolved',
        ],

        'waiting-for-school' => [
            'in-progress',
        ],

        'resolved' => [
            'closed',
        ],

        'closed' => [],
    ];

    public static function canTransition(
        string $from,
        string $to
    ): bool {
        return in_array(
            $to,
            self::TRANSITIONS[$from] ?? [],
            true
        );
    }

    public static function allowedTransitions(
        string $status
    ): array {
        return self::TRANSITIONS[$status] ?? [];
    }
}