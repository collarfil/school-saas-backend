<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketCategoryResource;
use App\Http\Resources\TicketPriorityResource;
use App\Http\Resources\TicketStatusResource;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;

class TicketLookupController extends Controller
{
    /**
     * Active ticket categories.
     */
    public function categories()
    {
        return TicketCategoryResource::collection(
            TicketCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Active priorities.
     */
    public function priorities()
    {
        return TicketPriorityResource::collection(
            TicketPriority::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Active statuses.
     *
     * Normally Super Admin dashboard only.
     */
    public function statuses()
    {
        return TicketStatusResource::collection(
            TicketStatus::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }
}