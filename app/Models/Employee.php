<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'role', 'school_id'];

    public function school(){
        return $this->belongsTo(School::class);
    }
    
    
    // Fix: Use 'employee_subjects' as table name and add school_id scope
    public function subjects() { 
        return $this->belongsToMany(Subject::class, 'employee_subjects')
                    ->withPivot('school_id')
                    ->wherePivot('school_id', $this->school_id);
    }
    
    // Fix: Use 'employee_grades' as table name and add school_id scope
    public function grades() { 
        return $this->belongsToMany(Grade::class, 'employee_grades')
                    ->withPivot('school_id')
                    ->wherePivot('school_id', $this->school_id);
    }
}
