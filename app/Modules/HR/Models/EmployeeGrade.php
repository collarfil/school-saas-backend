<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Academics\Models\Grade;
use App\Modules\HR\Models\Employee;
use App\Modules\Core\Models\School;

class EmployeeGrade extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_grades';

    protected $fillable = [
        'employee_id',
        'grade_id',
        'school_id'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
