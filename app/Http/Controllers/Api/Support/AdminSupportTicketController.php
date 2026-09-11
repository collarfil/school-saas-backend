<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Requests\ResolveSupportTicketRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignSupportTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Services\Support\TicketAssignmentService;
use App\Services\Support\TicketStatusService;
use Illuminate\Http\Request;

class AdminSupportTicketController extends Controller
{
    public function __construct(
        private SupportTicketService $ticketService,
        private TicketAssignmentService $assignmentService,
        private TicketStatusService $statusService
    ) {
    }

    /**
     * View all platform tickets.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', SupportTicket::class);

        $tickets = SupportTicket::query()
            ->with([
                'school',
                'category',
                'priority',
                'status',
                'creator',
                'assignee',
            ])
            ->latest()
            ->paginate(20);

        return SupportTicketResource::collection($tickets);
    }

    /**
     * View one ticket.
     */
    public function show(Request $request, string $uuid)
    {
        $ticket = $this->ticketService->findByUuid($uuid);

        $this->authorize('view', $ticket);

        $ticket->load([
            'school',
            'category',
            'priority',
            'status',
            'creator',
            'assignee',
            'messages.sender',
            'messages.attachments.uploader',
            'attachments.uploader',
            'statusHistories.oldStatus',
            'statusHistories.newStatus',
            'statusHistories.changedBy',
        ]);

        return new SupportTicketResource($ticket);
    }

    /**
     * Assign ticket to support staff.
     */
    public function assign(
        AssignSupportTicketRequest $request,
        string $uuid
    ) {
        $ticket = $this->ticketService->findByUuid($uuid);

        $this->authorize('assign', $ticket);

        $user = User::findOrFail(
            $request->validated('user_id')
        );

        /*
        |--------------------------------------------------------------------------
        | Ensure user is support staff
        |--------------------------------------------------------------------------
        */

        if (!$this->isSupportStaff($user)) {
            return response()->json([
                'message' => 'Ticket can only be assigned to support staff.',
            ], 422);
        }

        $ticket = $this->assignmentService->assign(
            ticket: $ticket,
            user: $user
        );

        return new SupportTicketResource($ticket);
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(
        UpdateTicketStatusRequest $request,
        string $uuid
    ) {
        $ticket = $this->ticketService->findByUuid($uuid);

        $this->authorize('changeStatus', $ticket);

        $data = $request->validated();

        $ticket = $this->statusService->changeStatus(
            ticket: $ticket,
            statusUuid: $data['status_uuid'],
            user: $request->user(),
            comment: $data['comment'] ?? null
        );

        return new SupportTicketResource($ticket);
    }

    /**
     * Resolve ticket.
     *
     * Resolution is stored before changing status.
     */
    public function resolve(
    ResolveSupportTicketRequest $request,
    string $uuid
    ) {
        $request->validate([
            'resolution' => [
                'required',
                'string',
                'min:5',
                'max:10000',
            ],
        ]);

        $ticket = $this->ticketService->findByUuid($uuid);

        $this->authorize('resolve', $ticket);

        $ticket->update([
            'resolution' => $request->resolution,
        ]);

        $resolvedStatus = \App\Models\TicketStatus::query()
            ->where('slug', 'resolved')
            ->where('is_active', true)
            ->firstOrFail();

        $ticket = $this->statusService->changeStatus(
            ticket: $ticket,
            statusUuid: $resolvedStatus->uuid,
            user: $request->user(),
            comment: 'Ticket resolved.'
        );

        return new SupportTicketResource($ticket);
    }

    private function isSupportStaff(User $user): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('support_staff');
        }

        return strtolower($user->role ?? '') === 'support_staff';
    }
}