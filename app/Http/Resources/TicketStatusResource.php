<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,

            'is_initial' => $this->is_initial,
            'is_final' => $this->is_final,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}