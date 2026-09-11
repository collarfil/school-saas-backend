<?php

namespace App\Http\Requests;

use App\Models\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status_uuid' => [
                'required',
                'uuid',
                Rule::exists(TicketStatus::class, 'uuid')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],

            'comment' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}