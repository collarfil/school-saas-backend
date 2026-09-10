<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'school_id',
        'created_by',
        'assigned_to',
        'ticket_category_id',
        'ticket_priority_id',
        'ticket_status_id',
        'ticket_number',
        'subject',
        'description',
        'resolution',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            $ticket->uuid ??= (string) Str::uuid();

            $ticket->ticket_number ??=
                'TKT-' . now()->format('Ymd') . '-' .
                strtoupper(Str::random(6));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category()
    {
        return $this->belongsTo(
            TicketCategory::class,
            'ticket_category_id'
        );
    }

    public function priority()
    {
        return $this->belongsTo(
            TicketPriority::class,
            'ticket_priority_id'
        );
    }

    public function status()
    {
        return $this->belongsTo(
            TicketStatus::class,
            'ticket_status_id'
        );
    }

    public function messages()
    {
        return $this->hasMany(
            TicketMessage::class,
            'ticket_id'
        )->orderBy('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(
            TicketAttachment::class,
            'ticket_id'
        );
    }

    public function statusHistories()
    {
        return $this->hasMany(
            TicketStatusHistory::class,
            'ticket_id'
        )->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | Tenant Scope
    |--------------------------------------------------------------------------
    */

    public function scopeForSchool(
        Builder $query,
        int $schoolId
    ): Builder {
        return $query->where('school_id', $schoolId);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isOpen(): bool
    {
        return $this->status?->slug === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status?->slug === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status?->slug === 'closed';
    }
}