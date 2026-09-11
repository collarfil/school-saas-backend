<?php

namespace App\Services\Support;

use App\Support\TicketWorkflow;
use App\Models\SupportTicket;
use App\Models\TicketStatus;

class TicketWorkflowService
{
    
    public function allowedStatuses(
        SupportTicket $ticket
    ) {
        $ticket->loadMissing('status');

        $currentSlug = $ticket->status?->slug;

        if (!$currentSlug) {
            return collect();
        }

       $allowedSlugs =
    TicketWorkflow::allowedTransitions($currentSlug);

        return TicketStatus::query()
            ->whereIn('slug', $allowedSlugs)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function allowedSlugs(
        SupportTicket $ticket
    ): array {
        $ticket->loadMissing('status');

        return TicketWorkflow::allowedTransitions(
    $ticket->status?->slug ?? ''
);
    }
}