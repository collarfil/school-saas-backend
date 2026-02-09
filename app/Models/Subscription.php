<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
    'school_id', 'pricing_id', 'plan_type', 'term', 'school_session', 'student_capacity',
    'amount', 'payment_reference', 'payment_status', 'payment_gateway',
    'payment_response', 'payment_date', 'valid_from', 'valid_until', 'status'
    ];

    protected $casts = [
        'payment_response' => 'array',
        'payment_date' => 'datetime',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'amount' => 'decimal:2',
        'student_capacity' => 'integer'
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function paystackTransactions()
    {
        return $this->hasMany(PaystackTransaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('valid_until', '>=', now());
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    // Helper Methods
    public function isActive()
    {
        return $this->status === 'active' && $this->valid_until >= now();
    }

    public function isExpired()
    {
        return $this->valid_until < now();
    }

    public function getRemainingDays()
    {
        return now()->diffInDays($this->valid_until);
    }
    // In Subscription model

    public function pricing()
    {
        return $this->belongsTo(SubscriptionPricing::class, 'pricing_id');
    }

    public function markAsPaid($paymentData = [])
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_date' => now(),
            'status' => 'active',
            'payment_response' => $paymentData
        ]);
    }
}