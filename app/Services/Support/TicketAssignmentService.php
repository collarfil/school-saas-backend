<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\User;

class TicketAssignmentService
{
    public function assign(
        SupportTicket $ticket,
        User $user
    ): SupportTicket {
        $ticket->update([
            'assigned_to' => $user->id,
        ]);

        return $ticket->fresh([
            'assignee',
            'status',
        ]);
    }

    public function unassign(
        SupportTicket $ticket
    ): SupportTicket {
        $ticket->update([
            'assigned_to' => null,
        ]);

        return $ticket->fresh();
    }
}