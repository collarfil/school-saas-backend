<?php

namespace App\Modules\Finance\Models;

use App\Models\School;
use App\Models\Student;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'student_id', 
        'fee_id', 
        'transaction_id',
        'amount_paid', 
        'payment_date',
        'status',
        'payment_reference', 
        'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount_paid' => 'decimal:2',
            'status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    { 
        return $this->belongsTo(Student::class); 
    }
    
    public function fee(): BelongsTo 
    { 
        return $this->belongsTo(Fee::class); 
    }
    
    public function transaction(): BelongsTo
    { 
        return $this->belongsTo(Transaction::class); 
    }

    public function scopeByReference($query, $reference)
    {
        return $query->where('payment_reference', $reference);
    }
    
    public function scopeGroupedPayments($query)
    {
        return $query->select('payment_reference')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('SUM(amount_paid) as total_amount')
            ->groupBy('payment_reference')
            ->orderByDesc('id');
    }

    public static function rules($forUpdate = false): array
    {
        $rules = [
            'student_id' => 'required|exists:students,id',
            'fee_id' => 'required|exists:fees,id',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'status' => 'required|in:paid,pending,failed,successful',
            'school_id' => 'required|exists:schools,id',
            'payment_reference' => 'nullable|string|unique:fee_payments,payment_reference',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,card,paystack,stripe,flutterwave,other,legacy',
        ];
        
        if ($forUpdate) {
            $rules['payment_reference'] = 'nullable|string';
        }
        
        return $rules;
    }
}