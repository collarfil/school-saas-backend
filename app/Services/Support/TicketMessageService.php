<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TicketMessageService
{
    public function __construct(
        private TicketAttachmentService $attachmentService
    ) {
    }

    public function create(
        SupportTicket $ticket,
        User $user,
        string $message,
        array $attachments = [],
        bool $isInternal = false
    ): TicketMessage {
        return DB::transaction(function () use (
            $ticket,
            $user,
            $message,
            $attachments,
            $isInternal
        ) {
            $ticketMessage = TicketMessage::create([
                'ticket_id' => $ticket->id,

                'sender_id' => $user->id,

                'sender_type' => $this->resolveSenderType($user),

                'message' => $message,

                'is_internal' => $isInternal,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Upload Attachments
            |--------------------------------------------------------------------------
            */

            foreach ($attachments as $attachment) {
                if ($attachment instanceof UploadedFile) {
                    $this->attachmentService->uploadToMessage(
                        ticket: $ticket,
                        message: $ticketMessage,
                        file: $attachment,
                        user: $user
                    );
                }
            }

            return $ticketMessage->fresh([
                'sender',
                'attachments.uploader',
            ]);
        });
    }

    private function resolveSenderType(User $user): string
    {
        /*
        |--------------------------------------------------------------------------
        | Adjust this to your existing role structure
        |--------------------------------------------------------------------------
        */

        if (
            method_exists($user, 'hasRole') &&
            $user->hasRole('super_admin')
        ) {
            return 'super_admin';
        }

        if (
            method_exists($user, 'hasRole') &&
            $user->hasRole('support_staff')
        ) {
            return 'support_staff';
        }

        return 'school_admin';
    }
}