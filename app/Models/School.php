<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner', 'name', 'email', 'phone', 'address', 'logo', 'is_unlocked'
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
    ];

    // Relationships
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

    // Business Logic
    public function hasActiveSubscription()
    {
        return $this->activeSubscription()->exists();
    }

    public function isSubscriptionExpired()
    {
        return !$this->hasActiveSubscription();
    }

    public function canAddMoreStudents()
    {
        if (!$this->hasActiveSubscription()) return false;
        
        $currentStudents = $this->users()->where('role', 'student')->count();
        $allowedCapacity = $this->activeSubscription->student_capacity;
        
        return $currentStudents < $allowedCapacity;
    }

    public function getRemainingStudentCapacity()
    {
        if (!$this->hasActiveSubscription()) return 0;
        
        $currentStudents = $this->users()->where('role', 'student')->count();
        return max(0, $this->activeSubscription->student_capacity - $currentStudents);
    }

    public function unlock()
    {
        $this->update(['is_unlocked' => true]);
        
        // Log the unlocking
        \Illuminate\Support\Facades\Log::info('School unlocked', [
            'school_id' => $this->id,
            'school_name' => $this->name,
            'unlocked_at' => now()
        ]);
        
        return $this;
    }

    public function lock()
    {
        $this->update(['is_unlocked' => false]);
        
        \Illuminate\Support\Facades\Log::info('School locked', [
            'school_id' => $this->id,
            'school_name' => $this->name,
            'locked_at' => now()
        ]);
        
        return $this;
    }

    // Scope for unlocked schools
    public function scopeUnlocked($query)
    {
        return $query->where('is_unlocked', true);
    }

    // Scope for locked schools
    public function scopeLocked($query)
    {
        return $query->where('is_unlocked', false);
    }

    // Check if school has any subscription (active or inactive)
    public function hasAnySubscription()
    {
        return $this->subscriptions()->exists();
    }

    // Get latest subscription regardless of status
    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class)->latest();
    }
}