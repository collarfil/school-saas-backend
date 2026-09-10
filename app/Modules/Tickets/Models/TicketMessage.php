<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'ticket_id',
        'sender_id',
        'sender_type',
        'message',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketMessage $message) {
            $message->uuid ??= (string) Str::uuid();
        });
    }

    public function ticket()
    {
        return $this->belongsTo(
            SupportTicket::class,
            'ticket_id'
        );
    }

    public function sender()
    {
        return $this->belongsTo(
            User::class,
            'sender_id'
        );
    }

    public function attachments()
    {
        return $this->hasMany(
            TicketAttachment::class,
            'ticket_message_id'
        );
    }
}