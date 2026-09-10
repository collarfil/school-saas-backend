<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'ticket_id',
        'old_ticket_status_id',
        'new_ticket_status_id',
        'changed_by',
        'comment',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketStatusHistory $history) {
            $history->uuid ??= (string) Str::uuid();
        });
    }

    public function ticket()
    {
        return $this->belongsTo(
            SupportTicket::class,
            'ticket_id'
        );
    }

    public function oldStatus()
    {
        return $this->belongsTo(
            TicketStatus::class,
            'old_ticket_status_id'
        );
    }

    public function newStatus()
    {
        return $this->belongsTo(
            TicketStatus::class,
            'new_ticket_status_id'
        );
    }

    public function changedBy()
    {
        return $this->belongsTo(
            User::class,
            'changed_by'
        );
    }
}