<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'message' => $this->message,

            'sender_type' => $this->sender_type,

            'is_internal' => $this->is_internal,

            'sender' => $this->whenLoaded(
                'sender',
                fn () => [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'email' => $this->sender->email,
                ]
            ),

            'attachments' => TicketAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}