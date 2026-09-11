<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketAttachmentService
{
    public function uploadToTicket(
        SupportTicket $ticket,
        UploadedFile $file,
        User $user
    ): TicketAttachment {
        return $this->store(
            ticket: $ticket,
            file: $file,
            user: $user
        );
    }

    public function uploadToMessage(
        SupportTicket $ticket,
        TicketMessage $message,
        UploadedFile $file,
        User $user
    ): TicketAttachment {
        return $this->store(
            ticket: $ticket,
            file: $file,
            user: $user,
            message: $message
        );
    }

    private function store(
        SupportTicket $ticket,
        UploadedFile $file,
        User $user,
        ?TicketMessage $message = null
    ): TicketAttachment {
        $extension = $file->getClientOriginalExtension();

        $fileName = Str::uuid() . '.' . $extension;

        $path = $file->storeAs(
            "support-tickets/{$ticket->uuid}",
            $fileName,
            'public'
        );

        return TicketAttachment::create([
            'ticket_id' => $ticket->id,

            'ticket_message_id' => $message?->id,

            'uploaded_by' => $user->id,

            'original_name' => $file->getClientOriginalName(),

            'file_name' => $fileName,

            'file_path' => $path,

            'mime_type' => $file->getMimeType(),

            'file_size' => $file->getSize(),
        ]);
    }

    public function delete(TicketAttachment $attachment): bool
    {
        if (
            $attachment->file_path &&
            Storage::disk('public')->exists($attachment->file_path)
        ) {
            Storage::disk('public')->delete(
                $attachment->file_path
            );
        }

        return $attachment->delete();
    }
}