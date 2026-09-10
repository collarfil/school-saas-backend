<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TicketStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'is_initial',
        'is_final',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_initial' => 'boolean',
        'is_final' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketStatus $status) {
            $status->uuid ??= (string) Str::uuid();
        });
    }

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class, 'ticket_status_id');
    }

    public function oldStatusHistories()
    {
        return $this->hasMany(
            TicketStatusHistory::class,
            'old_ticket_status_id'
        );
    }

    public function newStatusHistories()
    {
        return $this->hasMany(
            TicketStatusHistory::class,
            'new_ticket_status_id'
        );
    }
}