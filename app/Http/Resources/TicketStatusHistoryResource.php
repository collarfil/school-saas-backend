<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'old_status' => $this->whenLoaded(
                'oldStatus',
                fn () => new TicketStatusResource($this->oldStatus)
            ),

            'new_status' => $this->whenLoaded(
                'newStatus',
                fn () => new TicketStatusResource($this->newStatus)
            ),

            'changed_by' => $this->whenLoaded(
                'changedBy',
                fn () => [
                    'id' => $this->changedBy->id,
                    'name' => $this->changedBy->name,
                    'email' => $this->changedBy->email,
                ]
            ),

            'comment' => $this->comment,

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}