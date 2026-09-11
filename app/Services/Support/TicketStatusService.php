<?php

namespace App\Services\Support;

use App\Exceptions\InvalidTicketStatusTransitionException;
use App\Models\SupportTicket;
use App\Models\TicketStatus;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketStatusService
{
    /**
     * Allowed ticket workflow transitions.
     */
    private const TRANSITIONS = [

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

    public function changeStatus(
        SupportTicket $ticket,
        string $statusUuid,
        User $user,
        ?string $comment = null
    ): SupportTicket {
        return DB::transaction(function () use (
            $ticket,
            $statusUuid,
            $user,
            $comment
        ) {
            $ticket->loadMissing('status');

            $newStatus = TicketStatus::query()
                ->where('uuid', $statusUuid)
                ->where('is_active', true)
                ->firstOrFail();

            $currentStatus = $ticket->status;

            if (!$currentStatus) {
                throw new InvalidTicketStatusTransitionException(
                    'Ticket has no current status.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Same Status
            |--------------------------------------------------------------------------
            */

            if ($currentStatus->id === $newStatus->id) {
                throw new InvalidTicketStatusTransitionException(
                    'Ticket is already in this status.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Workflow
            |--------------------------------------------------------------------------
            */

            $this->validateTransition(
                currentStatus: $currentStatus->slug,
                newStatus: $newStatus->slug
            );

            /*
            |--------------------------------------------------------------------------
            | Update Ticket Status
            |--------------------------------------------------------------------------
            */

            $ticket->ticket_status_id = $newStatus->id;

            /*
            |--------------------------------------------------------------------------
            | Resolved Timestamp
            |--------------------------------------------------------------------------
            */

            if ($newStatus->slug === 'resolved') {
                $ticket->resolved_at = now();
            }

            /*
            |--------------------------------------------------------------------------
            | Closed Timestamp
            |--------------------------------------------------------------------------
            */

            if ($newStatus->slug === 'closed') {
                $ticket->closed_at = now();
            }

            $ticket->save();

            /*
            |--------------------------------------------------------------------------
            | Status History
            |--------------------------------------------------------------------------
            */

            TicketStatusHistory::create([
                'ticket_id' => $ticket->id,

                'old_ticket_status_id' => $currentStatus->id,

                'new_ticket_status_id' => $newStatus->id,

                'changed_by' => $user->id,

                'comment' => $comment,
            ]);

            return $ticket->fresh([
                'status',
                'statusHistories.oldStatus',
                'statusHistories.newStatus',
                'statusHistories.changedBy',
            ]);
        });
    }

    /**
     * Check whether transition is allowed.
     */
    private function validateTransition(
        string $currentStatus,
        string $newStatus
    ): void {
        $allowedTransitions =
            self::TRANSITIONS[$currentStatus] ?? [];

        if (!in_array(
            $newStatus,
            $allowedTransitions,
            true
        )) {
            throw new InvalidTicketStatusTransitionException(
                "Cannot move ticket from [{$currentStatus}] to [{$newStatus}]."
            );
        }
    }

    /**
     * Return allowed next statuses.
     */
    public function getAllowedTransitions(
        SupportTicket $ticket
    ): array {
        $ticket->loadMissing('status');

        if (!$ticket->status) {
            return [];
        }

        return self::TRANSITIONS[
            $ticket->status->slug
        ] ?? [];
    }

    public function getAllowedTransitionModels(
    SupportTicket $ticket
) {
    $allowedSlugs = $this->getAllowedTransitions($ticket);

    return TicketStatus::query()
        ->whereIn('slug', $allowedSlugs)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();
}
}