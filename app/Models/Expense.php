<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'amount', 
        'date', 
        'note',
        'school_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date'
    ];

    public function school(){
        return $this->belongsTo(School::class);
    }
}