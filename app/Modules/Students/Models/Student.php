<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    // FIX: Change 'parent_id' to 'parents_id' to match your migration
    protected $fillable = ['name',  'grade_id', 'email', 'admission_number', 'parents_id','gender', 'is_active','school_id','phone'];

    protected $casts = [
        
        'is_active' => 'boolean', // Add this
    ];

    public function school(){return $this->belongsTo(School::class);}
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Add this scope for inactive students
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function grade() { 
        return $this->belongsTo(Grade::class); 
    }
    
    // FIX: Update the relationship to use 'parents_id' as foreign key
    public function parent() { 
        return $this->belongsTo(Parents::class, 'parents_id'); 
    }
    
    public function results() { 
        return $this->hasMany(Result::class); 
    }
    
    public function feePayments() { 
        return $this->hasMany(FeePayment::class); 
    }
}