<?php
// app/Modules/Core/Models/School.php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner', 
        'name', 
        'email', 
        'phone', 
        'address', 
        'logo', 
        'is_unlocked',
        'principal_signature',
        'has_free_subscription',
        'subscription_type',
        'subscription_expires_at',
        'subscription_id'
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'has_free_subscription' => 'boolean',
        'subscription_expires_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function admin()
    {
        return $this->hasOne(User::class)->where('role', 'admin');
    }

    public function paystackTransactions()
    {
        return $this->hasMany(PaystackTransaction::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('valid_until', '>=', now())
            ->latest();
    }

    public function currentSubscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    // ========== SUBSCRIPTION METHODS ==========

    /**
     * Check if school has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        // Check if school has free subscription
        if ($this->has_free_subscription) {
            return true;
        }

        // Check if school has valid paid subscription
        if ($this->subscription_expires_at && $this->subscription_expires_at->isFuture()) {
            return true;
        }

        // Check through activeSubscription relationship
        if ($this->activeSubscription()->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Get subscription status
     */
    public function getSubscriptionStatus(): string
    {
        if ($this->has_free_subscription) {
            return 'free';
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isFuture()) {
            return 'active';
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            return 'expired';
        }

        if ($this->activeSubscription()->exists()) {
            return 'active';
        }

        return 'inactive';
    }

    /**
     * Get remaining days of subscription
     */
    public function getRemainingDays(): ?int
    {
        if ($this->has_free_subscription) {
            return null; // Free forever
        }

        if ($this->subscription_expires_at) {
            $days = now()->diffInDays($this->subscription_expires_at, false);
            return max(0, (int)$days);
        }

        $activeSub = $this->activeSubscription()->first();
        if ($activeSub && $activeSub->valid_until) {
            $days = now()->diffInDays($activeSub->valid_until, false);
            return max(0, (int)$days);
        }

        return null;
    }

    /**
     * Check if subscription is expired
     */
    public function isSubscriptionExpired(): bool
    {
        return !$this->hasActiveSubscription();
    }

    /**
     * Check if school can add more students
     */
    public function canAddMoreStudents(): bool
    {
        if (!$this->hasActiveSubscription()) {
            return false;
        }

        $currentStudents = $this->users()->where('role', 'student')->count();
        $allowedCapacity = $this->getStudentCapacity();

        return $currentStudents < $allowedCapacity;
    }

    /**
     * Get student capacity
     */
    public function getStudentCapacity(): int
    {
        // Check free subscription
        if ($this->has_free_subscription) {
            return 1000; // Unlimited for free schools
        }

        // Check paid subscription
        if ($this->currentSubscription) {
            return $this->currentSubscription->student_capacity ?? 100;
        }

        $activeSub = $this->activeSubscription()->first();
        if ($activeSub) {
            return $activeSub->student_capacity ?? 100;
        }

        return 0;
    }

    /**
     * Get remaining student capacity
     */
    public function getRemainingStudentCapacity(): int
    {
        if (!$this->hasActiveSubscription()) {
            return 0;
        }

        $currentStudents = $this->users()->where('role', 'student')->count();
        $capacity = $this->getStudentCapacity();

        return max(0, $capacity - $currentStudents);
    }

    /**
     * Unlock school
     */
    public function unlock(): self
    {
        $this->update(['is_unlocked' => true]);

        Log::info('School unlocked', [
            'school_id' => $this->id,
            'school_name' => $this->name,
            'unlocked_at' => now()
        ]);

        return $this;
    }

    /**
     * Lock school
     */
    public function lock(): self
    {
        $this->update(['is_unlocked' => false]);

        Log::info('School locked', [
            'school_id' => $this->id,
            'school_name' => $this->name,
            'locked_at' => now()
        ]);

        return $this;
    }

    /**
     * Check if school has any subscription
     */
    public function hasAnySubscription(): bool
    {
        return $this->subscriptions()->exists();
    }

    /**
     * Get latest subscription
     */
    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    // ========== SCOPES ==========

    public function scopeUnlocked($query)
    {
        return $query->where('is_unlocked', true);
    }

    public function scopeLocked($query)
    {
        return $query->where('is_unlocked', false);
    }

    public function scopeWithFreeSubscription($query)
    {
        return $query->where('has_free_subscription', true);
    }

    public function scopeWithPaidSubscription($query)
    {
        return $query->where('has_free_subscription', false)
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '>', now());
    }

    public function scopeWithExpiredSubscription($query)
    {
        return $query->where('has_free_subscription', false)
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<', now());
    }

    public function scopeWithNoSubscription($query)
    {
        return $query->where('has_free_subscription', false)
            ->whereNull('subscription_expires_at');
    }
}