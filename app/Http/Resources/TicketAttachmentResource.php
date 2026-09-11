<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,

            'url' => $this->file_path
                ? Storage::url($this->file_path)
                : null,

            'uploaded_by' => $this->whenLoaded(
                'uploader',
                fn () => [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}