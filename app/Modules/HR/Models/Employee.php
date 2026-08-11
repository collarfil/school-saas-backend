<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Core\Models\School;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Grade;
use App\Modules\HR\Models\User;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'role', 'school_id'];

    protected $appends = ['employee_type'];

    public function getEmployeeTypeAttribute()
    {
        return $this->role === 'teacher' ? 'teaching' : 'non_teaching';
    }

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
