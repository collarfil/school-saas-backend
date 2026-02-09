<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 
        'amount', 
        'method', // Changed from 'gateway' to 'method'
        'status',
        'school_id',
        'response_payload', // Keep if you need it
        'school_id'
    ];

    protected $casts = [
        'response_payload' => 'array',
        'amount' => 'decimal:2'
    ];

    public function school(){return  $this->belongsTo(School::class);}
    // Relationship with fee payments
    public function feePayment()
    {
        return $this->hasOne(FeePayment::class);
    }
}