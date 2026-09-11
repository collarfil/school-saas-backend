<?php

namespace App\Http\Requests;

use App\Models\TicketCategory;
use App\Models\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'category_uuid' => [
                'required',
                'uuid',
                Rule::exists(TicketCategory::class, 'uuid')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],

            'priority_uuid' => [
                'required',
                'uuid',
                Rule::exists(TicketPriority::class, 'uuid')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],

            'subject' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'min:10',
                'max:10000',
            ],
        ];
    }
}