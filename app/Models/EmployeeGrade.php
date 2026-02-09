<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeGrade extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_grades';

    protected $fillable = [
        'employee_id',
        'grade_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
