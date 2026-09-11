<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\TicketStatus;
use App\Services\Support\SupportTicketService;
use App\Services\Support\TicketStatusService;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(
        private SupportTicketService $ticketService,
        private TicketStatusService $statusService
    ) {
    }

    /**
     * List authenticated school's tickets.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $tickets = SupportTicket::query()
            ->where('school_id', $user->school_id)
            ->with([
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
     * Create a new support ticket.
     */
    public function store(StoreSupportTicketRequest $request)
    {
        $this->authorize('create', SupportTicket::class);

        $ticket = $this->ticketService->create(
            user: $request->user(),
            data: $request->validated(),
            attachments: $request->file('attachments', [])
        );

        return (new SupportTicketResource($ticket))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * View one ticket belonging to authenticated school.
     */
    public function show(Request $request, string $uuid)
    {
        $ticket = $this->ticketService->findForSchool(
            ticketUuid: $uuid,
            schoolId: $request->user()->school_id
        );

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
     * School Admin closes a RESOLVED ticket.
     */
    public function close(
        Request $request,
        string $uuid
    ) {
        $ticket = $this->ticketService->findForSchool(
            ticketUuid: $uuid,
            schoolId: $request->user()->school_id
        );

        $this->authorize('close', $ticket);

        if (!$ticket->isResolved()) {
            return response()->json([
                'message' => 'Only resolved tickets can be closed.',
            ], 422);
        }

        $closedStatus = TicketStatus::query()
            ->where('slug', 'closed')
            ->where('is_active', true)
            ->firstOrFail();

        $ticket = $this->statusService->changeStatus(
            ticket: $ticket,
            statusUuid: $closedStatus->uuid,
            user: $request->user(),
            comment: 'Ticket closed by school administrator.'
        );

        return new SupportTicketResource(
            $ticket->load([
                'category',
                'priority',
                'status',
                'creator',
                'assignee',
            ])
        );
    }
}