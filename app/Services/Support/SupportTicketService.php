<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    public function __construct(
        private TicketAttachmentService $attachmentService
    ) {
    }

    public function create(
        User $user,
        array $data,
        array $attachments = []
    ): SupportTicket {
        return DB::transaction(function () use (
            $user,
            $data,
            $attachments
        ) {
            /*
            |--------------------------------------------------------------------------
            | Tenant Protection
            |--------------------------------------------------------------------------
            */

            if (!$user->school_id) {
                throw new \RuntimeException(
                    'No school is associated with this user.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Resolve Category
            |--------------------------------------------------------------------------
            */

            $category = TicketCategory::query()
                ->where('uuid', $data['category_uuid'])
                ->where('is_active', true)
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Resolve Priority
            |--------------------------------------------------------------------------
            */

            $priority = TicketPriority::query()
                ->where('uuid', $data['priority_uuid'])
                ->where('is_active', true)
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Resolve Initial Status
            |--------------------------------------------------------------------------
            */

            $initialStatus = TicketStatus::query()
                ->where('is_initial', true)
                ->where('is_active', true)
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Create Ticket
            |--------------------------------------------------------------------------
            */

            $ticket = SupportTicket::create([
                'school_id' => $user->school_id,

                'created_by' => $user->id,

                'ticket_category_id' => $category->id,

                'ticket_priority_id' => $priority->id,

                'ticket_status_id' => $initialStatus->id,

                'subject' => $data['subject'],

                'description' => $data['description'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Upload Attachments
            |--------------------------------------------------------------------------
            */

            foreach ($attachments as $attachment) {
                if ($attachment instanceof UploadedFile) {
                    $this->attachmentService->uploadToTicket(
                        ticket: $ticket,
                        file: $attachment,
                        user: $user
                    );
                }
            }

            return $ticket->fresh([
                'school',
                'category',
                'priority',
                'status',
                'creator',
                'attachments.uploader',
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Tenant Scoped Lookup
    |--------------------------------------------------------------------------
    */

    public function findForSchool(
        string $ticketUuid,
        int $schoolId
    ): SupportTicket {
        return SupportTicket::query()
            ->where('uuid', $ticketUuid)
            ->where('school_id', $schoolId)
            ->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | Platform Lookup
    |--------------------------------------------------------------------------
    */

    public function findByUuid(
        string $ticketUuid
    ): SupportTicket {
        return SupportTicket::query()
            ->where('uuid', $ticketUuid)
            ->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | Close Ticket
    |--------------------------------------------------------------------------
    */

    public function close(
        SupportTicket $ticket
    ): SupportTicket {
        $ticket->update([
            'closed_at' => now(),
        ]);

        return $ticket->fresh([
            'status',
            'creator',
            'assignee',
        ]);
    }
}