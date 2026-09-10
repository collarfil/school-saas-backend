<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'ticket_id',
        'ticket_message_id',
        'uploaded_by',
        'original_name',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketAttachment $attachment) {
            $attachment->uuid ??= (string) Str::uuid();
        });
    }

    public function ticket()
    {
        return $this->belongsTo(
            SupportTicket::class,
            'ticket_id'
        );
    }

    public function message()
    {
        return $this->belongsTo(
            TicketMessage::class,
            'ticket_message_id'
        );
    }

    public function uploader()
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }
}