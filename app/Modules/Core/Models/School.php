<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
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

    /**
     * Boot method to auto-generate UUID on model creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($school) {
            if (empty($school->uuid)) {
                $school->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Use UUID as default route key binding for public endpoints
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /**
     * Find school by public UUID
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::where('uuid', $uuid)->first();
    }

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

    public function hasActiveSubscription(): bool
    {
        if ($this->has_free_subscription) {
            return true;
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isFuture()) {
            return true;
        }

        if ($this->activeSubscription()->exists()) {
            return true;
        }

        return false;
    }

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

    public function getRemainingDays(): ?int
    {
        if ($this->has_free_subscription) {
            return null;
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

    public function isSubscriptionExpired(): bool
    {
        return !$this->hasActiveSubscription();
    }

    public function canAddMoreStudents(): bool
    {
        if (!$this->hasActiveSubscription()) {
            return false;
        }

        $currentStudents = $this->users()->where('role', 'student')->count();
        $allowedCapacity = $this->getStudentCapacity();

        return $currentStudents < $allowedCapacity;
    }

    public function getStudentCapacity(): int
    {
        if ($this->has_free_subscription) {
            return 1000;
        }

        if ($this->currentSubscription) {
            return $this->currentSubscription->student_capacity ?? 100;
        }

        $activeSub = $this->activeSubscription()->first();
        if ($activeSub) {
            return $activeSub->student_capacity ?? 100;
        }

        return 0;
    }

    public function getRemainingStudentCapacity(): int
    {
        if (!$this->hasActiveSubscription()) {
            return 0;
        }

        $currentStudents = $this->users()->where('role', 'student')->count();
        $capacity = $this->getStudentCapacity();

        return max(0, $capacity - $currentStudents);
    }

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

    public function hasAnySubscription(): bool
    {
        return $this->subscriptions()->exists();
    }

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
    public function supportTickets()
    {
        return $this->hasMany(
            SupportTicket::class,
            'school_id'
        );
    }
}