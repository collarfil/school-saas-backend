<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaystackTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'subscription_id', 'reference', 'amount', 'currency',
        'channel', 'status', 'gateway_response', 'customer_email',
        'customer_id', 'metadata', 'gateway_data'
    ];

    protected $casts = [
        'metadata' => 'array',
        'gateway_data' => 'array',
        'amount' => 'decimal:2'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isSuccessful()
    {
        return $this->status === 'success';
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}