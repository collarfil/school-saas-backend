<?php

namespace App\Modules\Finance\Models;

use App\Models\School;
use App\Models\User;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'paid_by_user_id',
        'reference',
        'gateway_reference',
        'amount',
        'gateway_fee',
        'currency',
        'method',
        'status',
        'raw_response',
        'response_payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_fee' => 'decimal:2',
            'raw_response' => 'array',
            'response_payload' => 'array',
            'paid_at' => 'datetime',
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function feePayments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }
}