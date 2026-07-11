<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id', 
        'fee_id', 
        'transaction_id',
        'amount_paid', 
        'payment_date',
        'status',
        'school_id',
        'payment_reference', 
        'payment_method'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_paid' => 'decimal:2'
    ];

    public function school(){
        return $this->belongsTo(School::class);
    }

    public function student() { 
        return $this->belongsTo(Student::class); 
    }
    
    public function fee() { 
        return $this->belongsTo(Fee::class); 
    }
    
    public function transaction() { 
        return $this->belongsTo(Transaction::class); 
    }

    // Add scope for getting payments by reference
    public function scopeByReference($query, $reference)
    {
        return $query->where('payment_reference', $reference);
    }
    
    // Add scope for grouped payments
    public function scopeGroupedPayments($query)
    {
        return $query->select('payment_reference')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('SUM(amount_paid) as total_amount')
            ->groupBy('payment_reference')
            ->orderByDesc('id');
    }

    public static function rules($forUpdate = false)
    {
    $rules = [
        'student_id' => 'required|exists:students,id',
        'fee_id' => 'required|exists:fees,id',
        'amount_paid' => 'required|numeric|min:0',
        'payment_date' => 'required|date',
        'status' => 'required|in:paid,pending,failed',
        'school_id' => 'required|exists:schools,id',
        'payment_reference' => 'nullable|string|unique:fee_payments,payment_reference',
        'payment_method' => 'nullable|string|in:cash,bank_transfer,card,paystack,other,legacy',
    ];
    
    if ($forUpdate) {
        $rules['payment_reference'] = 'nullable|string';
    }
    
    return $rules;
    }
}
