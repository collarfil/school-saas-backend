<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Support\TicketStatusService;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'ticket_number' => $this->ticket_number,

            'subject' => $this->subject,

            'description' => $this->description,

            'resolution' => $this->resolution,

            'resolved_at' => $this->resolved_at?->toISOString(),

            'closed_at' => $this->closed_at?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | Ticket Information
            |--------------------------------------------------------------------------
            */

            'category' => $this->whenLoaded(
                'category',
                fn () => new TicketCategoryResource($this->category)
            ),

            'priority' => $this->whenLoaded(
                'priority',
                fn () => new TicketPriorityResource($this->priority)
            ),

            'status' => $this->whenLoaded(
                'status',
                fn () => new TicketStatusResource($this->status)
            ),

            /*
            |--------------------------------------------------------------------------
            | School
            |--------------------------------------------------------------------------
            */

            'school' => $this->whenLoaded(
                'school',
                fn () => [
                    'id' => $this->school->id,
                    'name' => $this->school->name,
                    'uuid' => $this->school->uuid ?? null,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Users
            |--------------------------------------------------------------------------
            */

            'creator' => $this->whenLoaded(
                'creator',
                fn () => [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ]
            ),

            'assignee' => $this->whenLoaded(
                'assignee',
                fn () => [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'email' => $this->assignee->email,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Messages
            |--------------------------------------------------------------------------
            */

            'messages' => TicketMessageResource::collection(
                $this->whenLoaded('messages')
            ),

            /*
            |--------------------------------------------------------------------------
            | Attachments
            |--------------------------------------------------------------------------
            */

            'attachments' => TicketAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),

            /*
            |--------------------------------------------------------------------------
            | Status History
            |--------------------------------------------------------------------------
            */

            'status_history' => TicketStatusHistoryResource::collection(
                $this->whenLoaded('statusHistories')
            ),

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}