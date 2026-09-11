<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use App\Services\Support\TicketMessageService;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    public function __construct(
        private SupportTicketService $ticketService,
        private TicketMessageService $messageService
    ) {
    }

    /**
     * Add a reply to ticket.
     */
    public function store(
        StoreTicketMessageRequest $request,
        string $uuid
    ) {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Resolve ticket depending on user role
        |--------------------------------------------------------------------------
        */

        if ($this->isSuperAdmin($user)) {

            $ticket = $this->ticketService->findByUuid($uuid);

        } else {

            $ticket = $this->ticketService->findForSchool(
                ticketUuid: $uuid,
                schoolId: $user->school_id
            );
        }

        $this->authorize('reply', $ticket);

        $message = $this->messageService->create(
            ticket: $ticket,
            user: $user,
            message: $request->validated('message'),
            attachments: $request->file('attachments', []),
            isInternal: false
        );

        return (new TicketMessageResource($message))
            ->response()
            ->setStatusCode(201);
    }

    private function isSuperAdmin($user): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('super_admin');
        }

        return strtolower($user->role ?? '') === 'super_admin';
    }
}